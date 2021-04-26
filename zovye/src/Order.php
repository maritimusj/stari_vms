<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\commission_balanceModelObj;
use zovye\model\userModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;
use zovye\model\order_goodsModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class Order extends State
{
    const OK = 0;
    const ERR_ORDER_NOT_FINISHED = -1;
    const ERR_INVALID_HARDWARE_VERSION = 1;
    const ERR_DEVICE_IS_BUSY = 2;
    const ERR_INVALID_DEVICE = 9;
    const ERR_MALFUNCTION_FAILURE = 10;
    const ERR_DEVICE_TIMEOUT = 11;
    const ERR_ORDER_PROCESSING = 12;
    const ERR_ORDER_MISSING = 13;
    const ERR_INTERNAL = 50;

    const NORMAL = 0;
    const REFUND = 1;

    protected static $title = [
        self::OK => '出货成功！',
        self::ERR_ORDER_NOT_FINISHED => '订单还没完成！',
        self::ERR_INVALID_HARDWARE_VERSION => '设备硬件版本不对！',
        self::ERR_DEVICE_IS_BUSY => '设备被占用！',
        self::ERR_INVALID_DEVICE => '无效的设备UID！',
        self::ERR_MALFUNCTION_FAILURE => '设备发生故障！',
        self::ERR_DEVICE_TIMEOUT => '设备响应超时！',
        self::ERR_ORDER_PROCESSING => '订单处理中！',
        self::ERR_ORDER_MISSING => '订单丢失！',
        self::ERR_INTERNAL => '系统错误！',
    ];

    //订单来源
    const PAY = 0;
    const ACCOUNT = 1;
    const SQM = 2;
    const VOUCHER = 10;    

    /**
     * @param array $condition
     * @return ModelObjFinderProxy
     */
    public static function query(array $condition = []): ModelObjFinderProxy
    {
        $finder = m('order')->where(We7::uniacid([]))->where($condition);
        return new ModelObjFinderProxy($finder);
    }

    /**
     * @param $cond
     * @return orderModelObj|null
     */
    public static function findOne($cond): ?orderModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($cond): bool
    {
        $cond = is_array($cond) ? $cond : ['order_id' => strval($cond)];
        return m('order')->exists($cond);
    }

    /**
     * @param array $data
     * @return orderModelObj|null
     */
    public static function create(array $data = []): ?orderModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('order')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('order')->create($data);
    }

    /**
     * @param $order_no
     * @param int $total
     * @return bool
     */
    public static function refundBy($order_no, $total = 0): bool
    {
        $pay_log = Pay::getPayLog($order_no);
        if ($pay_log) {
            $pay_result = $pay_log->getData('payResult');
            if ($pay_result['result'] == 'success' || $pay_log->getData('finished')) {
                //退款
                $res = Pay::refund($order_no, $total);

                $pay_log->setData(is_error($res) ? 'refund_fail' : 'refund', $res);
                $pay_log->save();

                return !$res && !is_error($res);
            }
        }

        return false;
    }

    public static function queryStatus($serialNO)
    {
        return CtrlServ::v2_query("goods/{$serialNO}", ["nostr" => microtime(true)]);
    }

    /**
     * @param deviceModelObj $device
     * @return orderModelObj
     */
    public static function getLastOrderOfDevice(deviceModelObj $device): ?orderModelObj
    {
        $query = self::query(['device_id' => $device->getId()]);
        return $query->orderBy('id desc')->findOne();
    }

    /**
     * @param userModelObj $user
     * @return orderModelObj
     */
    public static function getLastOrderOfUser(userModelObj $user): ?orderModelObj
    {
        $query = self::query(['openid' => $user->getOpenid()]);
        return $query->orderBy('id desc')->findOne();
    }

    public static function getCommissionDetail($id): array
    {
        $order = self::get($id);
        if (empty($order)) {
            return error(State::ERROR, "找不到这个订单！");
        }

        $query = CommissionBalance::query([
            'createtime >=' => $order->getCreatetime(),
            'createtime <' => $order->getCreatetime() + 3600,
            'extra LIKE' => '%{s:7:\"orderid\";i:' . $id . ';}%',
        ]);

        $result = [];
        /** @var commission_balanceModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $user = User::get($entry->getOpenid(), true);
            $result[] = [
                'user' => empty($user) ? [] : $user->profile(),
                'xval' => $entry->getXVal(),
                'createtime' => date("Y-m-d H:i:s", $entry->getCreatetime()),
            ];
        }
        return $result;
    }

    /**
     * 订单退款
     * @param $order_no
     * @param int $goods_num
     * @param array $refund_data
     * @return bool|array
     */
    public static function refund($order_no, $goods_num = 0, array $refund_data = [])
    {
        return Util::transactionDo(
            function () use ($order_no, $refund_data, $goods_num) {
                if (empty($order_no)) {
                    return error(State::ERROR, '订单号不正确!');
                }

                $order = Order::get($order_no, true);
                if (empty($order)) {
                    //尝试订单id查找订单
                    $order = Order::get(intval($order_no));
                    if (empty($order)) {
                        return error(State::FAIL, '找不到这个订单!');
                    }
                }

                $order_no = $order->getOrderNO();

                $pay_log = Pay::getPayLog($order_no);
                if (empty($pay_log)) {
                    return error(State::FAIL, '找不到支付信息!');
                }

                if ($pay_log->getData('refund')) {
                    return error(State::FAIL, '此订单已退款!');
                }

                $percent = 1;
                $total_refund = $pay_log->getPrice();

                $total = $pay_log->getTotal();
                if ($goods_num && $total > 1 && $goods_num < $total) {
                    $percent = $goods_num / $total;
                    $total_refund = min($order->getPrice(), $goods_num * $order->getGoodsPrice());
                }

                $total_remain = $total_refund;
                //处理已分佣的金额
                $commission = $order->getExtraData('commission');
                if ($commission) {
                    if (is_array($commission['keepers'])) {
                        foreach ($commission['keepers'] as $entry) {
                            $keeperUser = User::get($entry['openid'], true);
                            if (empty($keeperUser)) {
                                return error(State::ERROR, '找不到佣金用户，无法退款[201]');
                            }
                            $x_val = intval($entry['xval'] * $percent);
                            if ($x_val > 0) {
                                $total_remain -= $x_val;

                                $commission_balance = $keeperUser->getCommissionBalance();
                                if (empty($commission_balance)) {
                                    return error(State::ERROR, '找不到用户佣金帐户，无法退款[202]');
                                }

                                if ($commission_balance->total() < $x_val) {
                                    return error(State::FAIL, "运营人员{$keeperUser->getName()}帐户余额不足，无法退款！");
                                }

                                $r = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                    'orderid' => $order->getId(),
                                    'admin' => _W('username'),
                                ]);

                                if (empty($r) || !$r->update([], true)) {
                                    return error(State::FAIL, '返还用户佣金失败！');
                                }
                            }
                        }
                    }

                    if (is_array($commission['gsp'])) {
                        foreach ($commission['gsp'] as $entry) {
                            $user = User::get($entry['openid'], true);
                            if (empty($user)) {
                                return error(State::ERROR, '找不到佣金用户，无法退款[204]');
                            }
                            $x_val = intval($entry['xval'] * $percent);
                            if ($x_val > 0) {
                                $total_remain -= $x_val;

                                $commission_balance = $user->getCommissionBalance();
                                if (empty($commission_balance)) {
                                    return error(State::ERROR, '找不到用户佣金帐户，无法退款[205]');
                                }

                                if ($commission_balance->total() < $x_val) {
                                    return error(State::FAIL, "分佣帐户{$user->getName()}余额不足，无法退款！");
                                }

                                $rx = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                    'orderid' => $order->getId(),
                                    'admin' => _W('username'),
                                ]);

                                if (empty($rx) || !$rx->update([], true)) {
                                    return error(State::FAIL, '返还用户佣金失败！');
                                }
                            }
                        }
                    }

                    if (is_array($commission['agent'])) {
                        $x_val = intval($commission['agent']['xval'] * $percent);
                        if ($x_val > 0) {
                            $x_val = min($total_remain, $x_val);

                            $openid = strval($commission['agent']['openid']);
                            $agent = User::get($openid, true);
                            if (empty($agent)) {
                                return error(State::ERROR, '找不到设备代理商，无法退款[206]');
                            }

                            $commission_balance = $agent->getCommissionBalance();
                            if (empty($commission_balance)) {
                                return error(State::ERROR, '找不到设备代理商佣金帐户，无法退款[207]');
                            }

                            if ($commission_balance->total() < $x_val) {
                                return error(State::FAIL, '代理商帐户余额不足，无法退款！');
                            }

                            $rx = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                'orderid' => $order->getId(),
                                'admin' => _W('username'),
                            ]);

                            if (empty($rx) || !$rx->update([], true)) {
                                return error(State::FAIL, '代理商返还佣金失败！');
                            }
                        }
                    }
                }

                if (empty($refund_data['createtime'])) {
                    $refund_data['createtime'] = time();
                }

                $res = Pay::refund($order_no, $total_refund, $refund_data);
                if (is_error($res)) {
                    return $res;
                }

                $order->setExtraData('refund', $refund_data);
                $order->setRefund(Order::REFUND);

                if ($order->save()) {
                    return true;
                }

                return error(State::FAIL, '退款失败!');
            }
        );
    }

    /**
     * @param int|string $id
     * @param bool $is_orderNO
     * @return orderModelObj|null
     */
    public static function get($id, $is_orderNO = false): ?orderModelObj
    {
        /** @var orderModelObj[] $cache */
        static $cache = [];
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            if ($is_orderNO) {
                $res = self::query()->findOne(['order_id' => strval($id)]);
            } else {
                $res = self::query()->findOne(['id' => intval($id)]);
            }
            if ($res) {
                $cache[$res->getId()] = $res;
                $cache[$res->getOrderId()] = $res;
                return $res;
            }            
        }

        return null;
    }

    /**
     * @param orderModelObj $order
     * @param int $result
     * @param array $extra
     * @return order_goodsModelObj|null
     */
    public static function createGoodsLog(orderModelObj $order, int $result, array $extra = []): ?order_goodsModelObj
    {
        return m('order_goods')->create([
            'order_id' => $order->getId(),
            'goods_id' => $order->getGoodsId(),
            'result' => $result,
            'extra' => json_encode($extra),
        ]);
    }

    public static function goodsLogQuery(orderModelObj $order = null): base\modelFactory
    {
        $query = m('order_goods');
        if ($order) {
            $query->where(['order_id' => $order->getId()]);
        }

        return $query;
    }

    public static function format(orderModelObj $order, bool $detail = false): array
    {
        $userCharacter = User::getUserCharacter($order->getOpenid());
        $data = [
            'id' => $order->getId(),
            'num' => $order->getNum(),
            'price' => number_format($order->getPrice() / 100, 2),
            'discount' => number_format($order->getDiscount() / 100, 2),
            'balance' => $order->getBalance(),
            'ip' => $order->getIp(),
            'account' => $order->getAccount(),
            'orderId' => $order->getOrderId(),
            'createtime' => date('Y-m-d H:i:s', $order->getCreatetime()),
            'agentId' => $order->getAgentId(),
            'from' => $userCharacter,

        ];

        if ($detail) {
            $data['user'] = [];
            $data['agent'] = [];
            $data['device'] = [];

            $data['goods'] = $order->getExtraData('goods');
            $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);
            $goods = Goods::get($data['goods']['id']);
            if ($goods) {
                $data['goods']['extra'] = $goods->getAppendage();
            }

            //用户信息
            $user_openid = $order->getOpenid();
            $user_obj = User::get($user_openid, true);
            if ($user_obj) {
                $data['user'] = [
                    'id' => $user_obj->getId(),
                    'nickname' => $user_obj->getNickname(),
                    'avatar' => $user_obj->getAvatar(),
                ];
            }

            //设备信息
            $device_obj = $order->getDevice();
            if ($device_obj) {
                $data['device'] = [
                    'id' => $device_obj->getId(),
                    'name' => $device_obj->getName(),
                    'type' => $device_obj->getDeviceType(),
                    'qrcode' => strval($device_obj->getQrcode()),
                    'address' => $device_obj->getAddress(),
                ];
            }
            //代理商信息
            $agent_obj = $order->getAgent();
            if ($agent_obj) {
                $data['agentId'] = $agent_obj->getId();
                $data['agent'] = $agent_obj->profile();
            }

            //ip地址信息
            $ip_info = $order->getIpAddress();
            if ($ip_info) {
                $data['ip_info'] = "{$ip_info['data']['region']}{$ip_info['data']['city']}{$ip_info['data']['district']}";
            }

            $voucher_id = intval($order->getExtraData('voucher.id'));
            if ($voucher_id > 0) {
                $data['voucher'] = [
                    'id' => $voucher_id,
                    'code' => '&lt;n/a&gt;',
                ];
                $v = GoodsVoucher::getLogById($voucher_id);
                if ($v) {
                    $data['voucher']['code'] = $v->getCode();
                }
            }

            if ($data['price'] > 0) {
                $data['tips'] = ['text' => '支付', 'class' => 'wxpay'];
            } elseif ($data['balance'] > 0) {
                $data['tips'] = ['text' => '余额', 'class' => 'balancex'];
            } else {
                $data['tips'] = ['text' => '免费', 'class' => 'free'];
            }

            if ($data['price'] > 0 && $order->getExtraData('refund')) {
                $time = $order->getExtraData('refund.createtime');
                $time_formatted = date('Y-m-d H:i:s', $time);
                $data['refund'] = [
                    'title' => "退款时间：{$time_formatted}",
                    'reason' => $order->getExtraData('refund.message'),
                ];
            }
        }

        return $data;
    }
}
