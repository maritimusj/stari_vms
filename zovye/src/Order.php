<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use zovye\base\modelObj;
use zovye\base\modelObjFinder;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\userModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;
use zovye\model\pay_logsModelObj;
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
    const VOUCHER = 10;
    const BALANCE = 20;
    const CHARGING = 30;
    const CHARGING_UNPAID = 31;
    const FUELING = 40;
    const FUELING_UNPAID = 41;
    const FUELING_SOLO = 42;

    const FREE = 100;

    const PAY_STR = 'pay';
    const FREE_STR = 'free';
    const BALANCE_STR = 'balance';

    /**
     * @param mixed $condition
     * @return modelObjFinder
     */
    public static function query($condition = []): modelObjFinder
    {
        return m('order')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param mixed $cond
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

    public static function makeUID(userModelObj $user, deviceModelObj $device, $nonce = ''): string
    {
        return substr("U{$user->getId()}D{$device->getId()}$nonce".Util::random(32, true), 0, MAX_ORDER_NO_LEN);
    }

    public static function makeSerial(modelObj $obj, $serial = null): string
    {
        return sprintf("%d%05d%s", We7::uniacid(), $obj->getId(), $serial ?? time());
    }

    /**
     * @param $order_no
     * @param int $total
     * @return array|bool
     */
    public static function refundBy($order_no, int $total = 0)
    {
        //记录退款结果
        $pay_log = Pay::getPayLog($order_no);
        if ($pay_log) {

            //如果total < 0，表示退款金额需要减去total
            if ($total < 0) {
                $total = $pay_log->getPrice() + $total;
                if ($total < 1) {
                    return false;
                }
            }

            //退款
            $res = Pay::refund($order_no, $total);

            $total = $total != 0 ? $total : $pay_log->getPrice();
            $res['total'] = $total;

            $pay_log->setData(is_error($res) ? 'refund_fail' : 'refund', $res);
            if (!$pay_log->save()) {
                return err('保存数据失败！');
            }

            if (is_error($res)) {
                return $res;
            }

            return ['message' => '退款成功！', 'total' => $total];
        }

        return err('找不到支付记录！');
    }

    public static function queryStatus($serialNO)
    {
        return CtrlServ::getV2("goods/$serialNO", ["nostr" => microtime(true)]);
    }

    /**
     * 支持 deviceModelObj,agentModelObj,userModelObj,accountModelObj和全局WeApp
     * @param $obj
     * @param bool $fetch_order_obj
     * @return array|orderModelObj|null
     */
    public static function getFirstOrderOf($obj, bool $fetch_order_obj = false)
    {
        if ($obj instanceof deviceModelObj) {
            return self::getFirstOrderOfDevice($obj, $fetch_order_obj);
        }

        if ($obj instanceof agentModelObj) {
            return self::getFirstOrderOfAgent($obj, $fetch_order_obj);
        }

        if ($obj instanceof userModelObj) {
            return self::getFirstOrderOfUser($obj, $fetch_order_obj);
        }

        if ($obj instanceof accountModelObj) {
            return self::getFirstOrderOfAccount($obj, $fetch_order_obj);
        }

        if ($obj instanceof WeApp) {
            return self::getFirstOrder($fetch_order_obj);
        }

        return null;
    }

    /**
     * @param bool $fetch_order_obj
     * @return array|orderModelObj
     */
    public static function getLastOrder(bool $fetch_order_obj = false)
    {
        $query = Order::query();
        $query->orderBy('id DESC');

        /** @var orderModelObj $last_order */
        $last_order = $query->findOne();

        return $fetch_order_obj ? $last_order : [
            'id' => $last_order->getId(),
            'createtime' => $last_order->getCreatetime(),
        ];
    }

    /**
     * @param bool $fetch_order_obj
     * @return array|mixed|orderModelObj|null
     */
    public static function getFirstOrder(bool $fetch_order_obj = false)
    {
        $data = settings('stats.first_order');
        if ($data && $data['id']) {
            return $fetch_order_obj ? Order::get($data['id']) : $data;
        }
        /** @var orderModelObj $order */
        $order = self::query()->orderBy('id ASC')->findOne();
        if ($order) {
            $data = [
                'id' => $order->getId(),
                'createtime' => $order->getCreatetime(),
            ];
            updateSettings('stats.first_order', $data);

            return $fetch_order_obj ? $order : $data;
        }

        return null;
    }

    /**
     * @param deviceModelObj $device
     * @param bool $fetch_order_obj
     * @return array|orderModelObj|null
     */
    public static function getFirstOrderOfDevice(deviceModelObj $device, bool $fetch_order_obj = false)
    {
        $data = $device->settings('stats.first_order');
        if ($data && $data['id']) {
            return $fetch_order_obj ? Order::get($data['id']) : $data;
        }

        $query = self::query(['device_id' => $device->getId()]);
        /** @var orderModelObj $order */
        $order = $query->orderBy('id ASC')->findOne();
        if ($order) {
            $data = [
                'id' => $order->getId(),
                'createtime' => $order->getCreatetime(),
            ];
            $device->updateSettings('stats.first_order', $data);

            return $fetch_order_obj ? $order : $data;
        }

        return null;
    }

    /**
     * @param deviceModelObj $device
     * @return orderModelObj
     */
    public static function getLastOrderOfDevice(deviceModelObj $device): ?orderModelObj
    {
        $query = self::query(['device_id' => $device->getId()]);

        return $query->orderBy('id DESC')->findOne();
    }

    /**
     * @param agentModelObj $agent
     * @param bool $fetch_order_obj
     * @return array|orderModelObj|null
     */
    public static function getFirstOrderOfAgent(agentModelObj $agent, bool $fetch_order_obj = false)
    {
        $data = $agent->getFirstOrderData();
        if ($data && $data['id']) {
            return $fetch_order_obj ? Order::get($data['id']) : $data;
        }

        $query = self::query(['agent_id' => $agent->getId()]);
        /** @var orderModelObj $order */
        $order = $query->orderBy('id ASC')->findOne();
        if ($order) {
            $agent->setFirstOrderData($order);

            return $fetch_order_obj ? $order : [
                'id' => $order->getId(),
                'createtime' => $order->getCreatetime(),
            ];
        }

        return null;
    }

    /**
     * @param agentModelObj $agent
     * @return orderModelObj|null
     */
    public static function getLastOrderOfAgent(agentModelObj $agent): ?orderModelObj
    {
        $query = self::query(['agent_id' => $agent->getId()]);

        return $query->orderBy('id DESC')->findOne();
    }

    /**
     * @param accountModelObj $account
     * @param bool $fetch_order_obj
     * @return array|orderModelObj|null
     */
    public static function getFirstOrderOfAccount(accountModelObj $account, bool $fetch_order_obj = false)
    {
        $data = $account->getFirstOrderData();
        if ($data && $data['id']) {
            return $fetch_order_obj ? Order::get($data['id']) : $data;
        }
        $query = self::query(['account' => $account->getName()]);
        /** @var orderModelObj $order */
        $order = $query->orderBy('id ASC')->findOne();
        if ($order) {
            $account->setFirstOrderData($order);

            return $fetch_order_obj ? $order : [
                'id' => $order->getId(),
                'createtime' => $order->getCreatetime(),
            ];
        }

        return null;
    }

    /**
     * @param userModelObj $user
     * @param bool $fetch_order_obj
     * @return array|orderModelObj|null
     */
    public static function getFirstOrderOfUser(userModelObj $user, bool $fetch_order_obj = false)
    {
        $data = $user->settings('extra.first.order');
        if ($data && $data['id']) {
            return $fetch_order_obj ? Order::get($data['id']) : $data;
        }
        $query = self::query(['openid' => $user->getOpenid()]);
        /** @var orderModelObj $order */
        $order = $query->orderBy('id ASC')->findOne();
        if ($order) {
            $data = [
                'id' => $order->getId(),
                'createtime' => $order->getCreatetime(),
            ];
            $user->updateSettings('extra.first.order', $data);

            return $fetch_order_obj ? $order : $data;
        }

        return null;
    }

    /**
     * @param userModelObj $user
     * @return orderModelObj
     */
    public static function getLastOrderOfUser(userModelObj $user): ?orderModelObj
    {
        $query = self::query(['openid' => $user->getOpenid()]);

        return $query->orderBy('id DESC')->findOne();
    }

    public static function getCommissionDetail($id): array
    {
        $order = self::get($id);
        if (empty($order)) {
            return err("找不到这个订单！");
        }

        $query = CommissionBalance::query([
            'createtime >=' => $order->getCreatetime(),
            'createtime <' => $order->getCreatetime() + 3600,
            'extra LIKE' => '%{s:7:\"orderid\";i:'.$id.';}%',
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

    public static function refund2($order_no, $total, array $refund_data = [])
    {
        return DBUtil::transactionDo(function () use ($order_no, $total, $refund_data) {
            $order = Order::get($order_no, true);
            if (empty($order)) {
                //尝试订单id查找订单
                $order = Order::get(intval($order_no));
                if (empty($order)) {
                    return err('找不到这个订单!');
                }
            }

            if ($order->isFuelingOrder()) {
                $cardUID = $order->getExtraData('card', '');
                //todo 退款处理
            }

            $order_no = $order->getOrderNO();

            $pay_log = Pay::getPayLog($order_no);
            if (empty($pay_log)) {
                return err('找不到支付信息!');
            }

            $percent = $total / $pay_log->getPrice();
            $remain = $total;

            //处理已分佣的金额
            $commission = $order->getExtraData('commission');
            if ($commission) {
                if (is_array($commission['keepers'])) {
                    foreach ($commission['keepers'] as $entry) {
                        $keeperUser = User::get($entry['openid'], true);
                        if (empty($keeperUser)) {
                            return err('找不到佣金用户，无法退款[201]');
                        }
                        $x_val = intval(round($entry['xval'] * $percent));
                        if ($x_val > 0) {
                            $remain -= $x_val;

                            $commission_balance = $keeperUser->getCommissionBalance();
                            if ($commission_balance->total() < $x_val) {
                                return err("运营人员{$keeperUser->getName()}账户余额不足，无法退款！");
                            }

                            $r = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                'orderid' => $order->getId(),
                                'admin' => _W('username'),
                            ]);

                            if (empty($r) || !$r->update([], true)) {
                                return err('返还用户佣金失败！');
                            }
                        }
                    }
                }

                if (is_array($commission['gsp'])) {
                    foreach ($commission['gsp'] as $entry) {
                        $user = User::get($entry['openid'], true);
                        if (empty($user)) {
                            return err('找不到佣金用户，无法退款[204]');
                        }
                        $x_val = intval(round($entry['xval'] * $percent));
                        if ($x_val > 0) {
                            $remain -= $x_val;

                            $commission_balance = $user->getCommissionBalance();
                            if ($commission_balance->total() < $x_val) {
                                return err("{$user->getName()}账户余额不足，无法退款！");
                            }

                            $rx = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                'orderid' => $order->getId(),
                                'admin' => _W('username'),
                            ]);

                            if (empty($rx) || !$rx->update([], true)) {
                                return err('返还用户佣金失败！');
                            }
                        }
                    }
                }

                if (is_array($commission['agent'])) {
                    $x_val = intval(round($commission['agent']['xval'] * $percent));
                    if ($x_val > 0) {
                        $x_val = min($remain, $x_val);

                        $openid = strval($commission['agent']['openid']);
                        $agent = User::get($openid, true);
                        if (empty($agent)) {
                            return err('找不到设备代理商，无法退款[206]');
                        }

                        $commission_balance = $agent->getCommissionBalance();
                        if ($commission_balance->total() < $x_val) {
                            return err('代理商账户余额不足，无法退款！');
                        }

                        $rx = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                            'orderid' => $order->getId(),
                            'admin' => _W('username'),
                        ]);

                        if (empty($rx) || !$rx->update([], true)) {
                            return err('代理商返还佣金失败！');
                        }
                    }
                }
            }

            if (empty($refund_data['createtime'])) {
                $refund_data['createtime'] = time();
            }

            $res = Pay::refund($order_no, $total, $refund_data);
            if (is_error($res)) {
                return $res;
            }

            $order->setExtraData(
                'refund',
                array_merge($refund_data, [
                    'total' => $total,
                ])
            );

            $order->setRefund(Order::REFUND);

            if ($order->save()) {
                return true;
            }

            return err('退款失败!');
        });
    }

    /**
     * 订单退款
     * @param $order_no
     * @param int $goods_num
     * @param array $refund_data
     * @return bool|array
     */
    public static function refund($order_no, int $goods_num = 0, array $refund_data = [])
    {
        return DBUtil::transactionDo(
            function () use ($order_no, $refund_data, $goods_num) {
                if (empty($order_no)) {
                    return err('订单号不正确!');
                }

                $order = Order::get($order_no, true);
                if (empty($order)) {
                    //尝试订单id查找订单
                    $order = Order::get(intval($order_no));
                    if (empty($order)) {
                        return err('找不到这个订单!');
                    }
                }

                $order_no = $order->getOrderNO();

                $pay_log = Pay::getPayLog($order_no);
                if (empty($pay_log)) {
                    return err('找不到支付信息!');
                }

                if ($pay_log->getData('refund')) {
                    return err('此订单已退款!');
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
                                return err('找不到该用户，无法退款[201]');
                            }
                            $x_val = intval(round($entry['xval'] * $percent));
                            if ($x_val > 0) {
                                $total_remain -= $x_val;

                                $commission_balance = $keeperUser->getCommissionBalance();
                                if ($commission_balance->total() < $x_val) {
                                    return err("运营人员{$keeperUser->getName()}账户余额不足，无法退款！");
                                }

                                $r = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                    'orderid' => $order->getId(),
                                    'admin' => _W('username'),
                                ]);

                                if (empty($r) || !$r->update([], true)) {
                                    return err('返还用户佣金失败！');
                                }
                            }
                        }
                    }

                    if (is_array($commission['gsp'])) {
                        foreach ($commission['gsp'] as $entry) {
                            $user = User::get($entry['openid'], true);
                            if (empty($user)) {
                                return err('找不到该用户，无法退款[204]');
                            }
                            $x_val = intval(round($entry['xval'] * $percent));
                            if ($x_val > 0) {
                                $total_remain -= $x_val;

                                $commission_balance = $user->getCommissionBalance();
                                if ($commission_balance->total() < $x_val) {
                                    return err("{$user->getName()}账户余额不足，无法退款！");
                                }

                                $rx = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                    'orderid' => $order->getId(),
                                    'admin' => _W('username'),
                                ]);

                                if (empty($rx) || !$rx->update([], true)) {
                                    return err('返还用户佣金失败！');
                                }
                            }
                        }
                    }

                    if (is_array($commission['agent'])) {
                        $x_val = intval(round($commission['agent']['xval'] * $percent));
                        if ($x_val > 0) {
                            $x_val = min($total_remain, $x_val);

                            $openid = strval($commission['agent']['openid']);
                            $agent = User::get($openid, true);
                            if (empty($agent)) {
                                return err('找不到设备代理商，无法退款[206]');
                            }

                            $commission_balance = $agent->getCommissionBalance();
                            if ($commission_balance->total() < $x_val) {
                                return err('代理商账户余额不足，无法退款！');
                            }

                            $rx = $commission_balance->change(0 - $x_val, CommissionBalance::ORDER_REFUND, [
                                'orderid' => $order->getId(),
                                'admin' => _W('username'),
                            ]);

                            if (empty($rx) || !$rx->update([], true)) {
                                return err('代理商返还佣金失败！');
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

                $order->setExtraData(
                    'refund',
                    array_merge($refund_data, [
                        'total' => $total_refund,
                    ])
                );

                $order->setRefund(Order::REFUND);

                if ($order->save()) {
                    return true;
                }

                return err('退款失败!');
            }
        );
    }

    /**
     * @param int|string $id
     * @param bool $is_orderNO
     * @return orderModelObj|null
     */
    public static function get($id, bool $is_orderNO = false): ?orderModelObj
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

    public static function format(orderModelObj $order, bool $detail = false): array
    {
        $userCharacter = User::getUserCharacter($order->getOpenid());

        $data = [
            'id' => $order->getId(),
            'src' => $order->getSrc(),
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
            'remark' => $order->getExtraData('remark', ''),
        ];

        if ($order->isChargingOrder()) {
            $data['type'] = 'charging';
        } elseif ($order->isFuelingOrder()) {
            $data['type'] = 'fueling';
        } else {
            $data['type'] = 'normal';
        }

        if ($detail) {
            $data['user'] = [];
            $data['agent'] = [];
            $data['device'] = [];

            if ($order->isChargingOrder()) {

                $group = $order->getExtraData('group');
                if ($group) {
                    $data['group'] = $group;
                    $data['charging'] = $order->getExtraData('charging', []);
                    $data['charging']['chargerID'] = $order->getChargerID();
                    if (!$order->isChargingFinished()) {
                        $device = $order->getDevice();
                        if ($device) {
                            $chargerID = $order->getChargerID();
                            $charging_now_data = ChargingNowData::getByDevice($device, $chargerID);
                            if ($charging_now_data && $charging_now_data->getSerial() == $data['orderId']) {
                                $data['charging']['status'] = $device->getChargerStatusData($chargerID);
                            }
                        }
                    } else {
                        $timeout = $order->getExtraData('timeout', []);
                        if ($timeout) {
                            $data['charging']['timeout'] = $timeout;
                        }
                    }
                }

                $data['pay'] = (array)$order->getExtraData('card', []);
                $data['BMS']['timeout'] = $order->isChargingBMSReportTimeout();

            } elseif ($order->isFuelingOrder()) {

                $data['num'] = number_format($data['num'], 2, '.', '');
                $data['goods'] = $order->getExtraData('goods');
                $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);
                $data['fueling'] = $order->getExtraData('fueling', []);
                $data['fueling']['chargerID'] = $order->getChargerID();
                if (!$order->isFuelingFinished()) {
                    $device = $order->getDevice();
                    if ($device) {
                        $chargerID = $order->getChargerID();
                        if ($device->fuelingNOWData($chargerID, 'serial', '') == $data['orderId']) {
                            $data['fueling']['status'] = $device->getFuelingStatusData($chargerID);
                        }
                    }
                } else {
                    $timeout = $order->getExtraData('timeout', []);
                    if ($timeout) {
                        $data['fueling']['result'] = [
                            're' => -1,
                            'message' => $timeout['reason'] ?? '设备超时！',
                        ];
                    }
                }
                if ($order->getSrc() == Order::FUELING_SOLO) {
                    $data['tips'] = ['text' => '单机', 'class' => 'solo'];
                } else {
                    $data['pay'] = (array)$order->getExtraData('card', []);
                    if ($data['pay']['type'] == UserCommissionBalanceCard::getTypename()) {
                        $data['tips'] = ['text' => '余额', 'class' => 'balancex'];
                    } elseif ($data['pay']['type'] == pay_logsModelObj::getTypename()) {
                        $data['tips'] = ['text' => '支付', 'class' => 'wxpay'];
                    } elseif ($data['pay']['type'] == VIPCard::getTypename()) {
                        $data['tips'] = ['text' => 'VIP', 'class' => 'vip'];
                    }
                }
            } else {
                $goods = $order->getExtraData('goods');
                if (!empty($goods)) {
                    $data['goods'] = $goods;
                    $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);
                    $goods = Goods::get($data['goods']['id']);
                    if ($goods) {
                        $data['goods']['extra'] = $goods->getAppendage();
                    }
                } else {
                    $package = $order->getExtraData('package');
                    if ($package) {
                        $data['package'] = $package;
                        foreach ($data['package']['list'] as $index => $goods) {
                            $data['package']['list'][$index]['image'] = Util::toMedia($goods['image'], true);
                            $goods = Goods::get($goods['id']);
                            if ($goods) {
                                $data['package']['list'][$index]['extra'] = $goods->getAppendage();
                            }
                        }
                    }
                }
            }

            //用户信息
            $user_openid = $order->getOpenid();
            $user_obj = User::get($user_openid, true);
            if ($user_obj) {
                $data['user'] = [
                    'id' => $user_obj->getId(),
                    'nickname' => $user_obj->getNickname(),
                    'avatar' => $user_obj->getAvatar(),
                    'mobile' => $user_obj->getMobile(),
                ];
            }

            //设备信息
            $device_obj = $order->getDevice();
            if ($device_obj) {
                $data['device'] = [
                    'id' => $device_obj->getId(),
                    'name' => $device_obj->getName(),
                    'imei' => $device_obj->getImei(),
                    'type' => $device_obj->getDeviceType(),
                    'qrcode' => $device_obj->getQrcode(),
                    'address' => $device_obj->getAddress(),
                ];
            }

            //代理商信息
            $agent_obj = $order->getAgent();
            if ($agent_obj) {
                $data['agentId'] = $agent_obj->getId();
                $data['agent'] = $agent_obj->profile();
            }

            $data['is_zero_bonus'] = $order->isZeroBonus();

            //ip地址信息
            $ip_info = $order->getIpAddress();
            if ($ip_info) {
                $data['ip_info'] = "{$ip_info['data']['province']}{$ip_info['data']['city']}{$ip_info['data']['district']}";
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

            if (empty($data['tips'])) {
                if ($data['price'] > 0 || $data['charging']) {
                    $data['tips'] = ['text' => '支付', 'class' => 'wxpay'];
                } elseif ($data['balance'] > 0) {
                    $data['tips'] = ['text' => '积分', 'class' => 'balanceex'];
                } else {
                    $data['tips'] = ['text' => '免费', 'class' => 'free'];
                }
            }

            $refund = $order->getExtraData('refund');
            if ($refund) {
                $time_formatted = date('Y-m-d H:i:s', $refund['createtime']);
                $data['refund'] = [
                    'title' => "退款时间：$time_formatted",
                    'reason' => $refund['message'] ?? '未知',
                ];
            }

            $reward = $order->getExtraData('reward');
            if ($reward) {
                $data['reward'] = $reward;
            }

            if (App::isSmsPromoEnabled()) {
                $promo = $order->getExtraData('promo');
                if ($promo) {
                    $data['promo'] = $promo;
                }
            }
        }

        return $data;
    }

    public static function getList(userModelObj $user, $way, $page, $page_size = DEFAULT_PAGE_SIZE): array
    {
        $query = self::query();

        $condition = [];

        //指定用户
        $condition['openid'] = $user->getOpenid();

        if ($way == 'free') {
            if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
                $condition['src'] = [Order::FREE, Order::ACCOUNT, Order::BALANCE];
            } else {
                $condition['src'] = [Order::FREE, Order::ACCOUNT];
            }
        } elseif ($way == 'pay') {
            if (App::isBalanceEnabled() && Balance::isPayOrder()) {
                $condition['src'] = [Order::PAY, Order::BALANCE];
            } else {
                $condition['src'] = Order::PAY;
            }
        } elseif ($way == 'balance') {
            $condition['src'] = Order::BALANCE;
        }

        $query->where($condition);

        $page = max(1, $page);
        $page_size = max(1, $page_size);

        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $result = [];

        $balance_enabled = App::isBalanceEnabled();

        /** @var orderModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'num' => $entry->getNum(),
                'price' => number_format($entry->getPrice() / 100, 2),
                'ip' => $entry->getIp(),
                'account' => $entry->getAccount(),
                'orderId' => $entry->getOrderId(),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'agentId' => $entry->getAgentId(),
            ];

            if ($balance_enabled && $entry->getBalance() > 0) {
                $data['balance'] = $entry->getBalance();
            }

            //商品
            $data['goods'] = $entry->getExtraData('goods', []);

            //设备信息
            $device_id = $entry->getDeviceId();
            $device_obj = Device::get($device_id);
            if ($device_obj) {
                $data['device'] = [
                    'name' => $device_obj->getName(),
                    'id' => $device_obj->getId(),
                ];
            }

            $src = $entry->getSrc();
            if ($src == Order::PAY) {
                $data['type'] = '支付订单';
                $data['tips'] = ['text' => '支付', 'class' => 'wxpay'];
            } elseif ($src == Order::BALANCE) {
                $data['type'] = '积分订单';
                $data['tips'] = ['text' => '积分', 'class' => 'balance'];
            } else {
                $data['type'] = '免费订单';
                $data['tips'] = ['text' => '免费', 'class' => 'free'];
            }

            if ($src == Order::PAY && $entry->getExtraData('refund')) {
                $time = $entry->getExtraData('refund.createtime');
                $time_formatted = date('Y-m-d H:i:s', $time);
                $data['refund'] = "已退款，退款时间：$time_formatted";
                $data['clr'] = '#8bc34a';
            }

            $pay_result = $entry->getExtraData('payResult');
            if ($pay_result['result'] === 'success') {
                $data['uniontid'] = $pay_result['uniontid'] ?? $pay_result['transaction_id'];
            }

            //出货结果
            $data['result'] = $entry->getExtraData('pull.result', []);

            if (is_error($data['result'])) {
                $data['status'] = '故障';
            } else {
                $data['status'] = '成功';
            }

            if ($data['refund']) {
                $data['status'] = '已退款';
            }

            if (User::isAliUser($entry->getOpenid())) {
                $pay_type = '支付宝';
            } elseif (User::isWxUser($entry->getOpenid())) {
                $pay_type = '微信';
            } elseif (User::isWXAppUser($entry->getOpenid())) {
                $pay_type = '微信小程序';
            } else {
                $pay_type = '未知';
            }

            $data['pay_type'] = $pay_type;
            $result[] = $data;
        }

        return [
            'total' => $total,
            'page' => $page,
            'page_size' => $page_size,
            'list' => $result,
        ];
    }

    /**
     * @return string[]
     */
    public static function getExportHeaders($onlyKeys = false): array
    {
        static $headers = [
            'ID' => 'ID',
            'order_no' => '订单号',
            'pay_no' => '支付号',
            'pay_type' => '支付类型',
            'username' => '用户名',
            'openid' => '用户openid',
            'sex' => '用户性别',
            'region' => '用户区域',
            'goods_id' => '商品ID',
            'goods_name' => '商品名称',
            'goods_num' => '商品数量',
            'goods_price' => '商品价格',
            'way' => '购买方式',
            'price' => '支付金额',
            'refund' => '是否退款',
            'refund_time' => '退款时间',
            'account_title' => '公众号',
            'agent' => '代理商名称',
            'device_title' => '设备名称',
            'device_imei' => '设备IMEI',
            'ip' => 'ip地址',
            'address' => '定位地址',
            'createtime' => '创建时间',
        ];

        return $onlyKeys ? array_keys($headers) : $headers;
    }

    public static function getExportQuery($params = [])
    {
        $agent_openid = $params['agent_openid'] ?? false;
        $account_id = $params['account_id'] ?? false;
        $device_id = $params['device_id'] ?? false;
        $device_uid = $params['device_uid'] ?? false;

        $query = Order::query();
        if ($agent_openid) {
            $agent = User::get($agent_openid, true);
            if (empty($agent)) {
                return err('找不到这个代理商！');
            }
            $query->where(['agent_id' => $agent->getId()]);
        }

        if ($account_id) {
            $account = Account::get($account_id);
            if (empty($account)) {
                return err('找不到指定的公众号！');
            }
            $query->where(['account' => $account->getName()]);
        }

        if ($device_id) {
            $device = Device::get($device_id);
            if (empty($device)) {
                return err('找不到指定的设备！');
            }
            $query->where(['device_id' => $device->getId()]);
        }

        if ($device_uid) {
            $device = Device::get($device_uid, true);
            if (empty($device)) {
                return err('找不到指定的设备！');
            }
            $query->where(['device_id' => $device->getId()]);
        }

        $date_start = $params['start'] ?? false;
        if ($date_start) {
            $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_start.' 00:00:00');
        }

        if (empty($s_date)) {
            $s_date = new DateTime('first day of this month 00:00:00');
        }

        $date_end = $params['end'] ?? false;
        if ($date_end) {
            $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_end.' 00:00:00');
        }
        if (empty($e_date)) {
            $e_date = new DateTime();
        }

        $e_date = $e_date->modify('next day 00:00:00');

        $query->where([
            'createtime >=' => $s_date->getTimestamp(),
            'createtime <' => $e_date->getTimestamp(),
        ]);

        return $query;
    }

    public static function export($filename, $query, $headers = [])
    {
        if (empty($headers)) {
            $headers = array_keys(self::getExportHeaders());
        } else {
            array_unshift($headers, 'ID');
        }

        $result = [];
        $last_id = 0;

        /** @var orderModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $last_id = $entry->getId();

            $user = User::get($entry->getOpenid(), true);
            $device = Device::get($entry->getDeviceId());
            if ($device && !$device->isChargingDevice()) {
                $goods = Goods::data($entry->getGoodsId());
            } else {
                $goods = [];
            }

            $data = [];

            foreach ($headers as $header) {
                switch ($header) {
                    case 'ID':
                        $data[$header] = $entry->getId();
                        break;
                    case 'order_no':
                        $data[$header] = 'NO.'.$entry->getOrderId();
                        break;
                    case 'pay_no':
                        $pay_result = $entry->getExtraData('payResult');
                        if ($pay_result) {
                            if (isset($pay_result['uniontid'])) {
                                $data[$header] = '\''.$pay_result['uniontid'];
                            } elseif (isset($pay_result['transaction_id'])) {
                                $data[$header] = '\''.$pay_result['transaction_id'];
                            } else {
                                $data[$header] = '';
                            }
                        } else {
                            $data[$header] = '';
                        }
                        break;
                    case 'price':
                        $data[$header] = number_format($entry->getPrice() / 100, 2, '.', '');
                        break;
                    case 'pay_type':
                        $data[$header] = User::getUserCharacter($entry->getUser())['title'];
                        break;
                    case 'openid':
                        $data[$header] = $user ? $user->getOpenid() : '';
                        break;
                    case 'username':
                        $data[$header] = $user ? str_replace('"', '', $user->getName()) : '';
                        break;
                    case 'sex':
                        if ($user) {
                            $profile = $user->profile();
                            $data[$header] = $profile['sex'] == 1 ? '男' : ($profile['sex'] == 2 ? '女' : '未知');
                        } else {
                            $data[$header] = '';
                        }
                        break;
                    case 'region':
                        if ($user) {
                            $profile = $user->profile();
                            $data[$header] = "{$profile['country']} {$profile['province']} {$profile['city']}";
                        } else {
                            $data[$header] = '';
                        }
                        break;
                    case 'ip':
                        $data[$header] = $entry->getIp();
                        break;
                    case 'address':
                        $info = $entry->get('ip_info', []);
                        if ($info) {
                            $json = is_string($info) ? json_decode($info, true) : $info;
                            if ($json) {
                                $data[$header] = "{$json['data']['region']}{$json['data']['city']}{$json['data']['district']}";
                            } else {
                                $data[$header] = '';
                            }
                        } else {
                            $data[$header] = '';
                        }
                        break;
                    case 'goods_id':
                        $data[$header] = str_replace('"', '', $goods['id']);
                        break;
                    case 'goods_name':
                        if ($device && $device->isChargingDevice()) {
                            $data[$header] = "充电";
                        } else {
                            $data[$header] = str_replace('"', '', $goods['name']);
                        }
                        break;
                    case 'goods_num':
                        if ($device && $device->isChargingDevice()) {
                            $data[$header] = $entry->getChargingRecord('total', 0).'度';
                        } else {
                            $data[$header] = $entry->getNum();
                        }
                        break;
                    case 'goods_price':
                        if ($device && $device->isChargingDevice()) {
                            $data[$header] = $entry->getPrice();
                        } else {
                            $data[$header] = number_format($entry->getGoodsPrice() / 100, 2, '.', '');
                        }
                        break;
                    case 'way':
                        if ($entry->getPrice() > 0) {
                            $data[$header] = '支付';
                        } else {
                            $data[$header] = '免费';
                        }
                        break;
                    case 'refund':
                        if ($entry->getPrice() > 0 && $entry->getExtraData('refund')) {
                            $data[$header] = '已退款';
                        } else {
                            $data[$header] = '';
                        }
                        break;
                    case 'refund_time':
                        if ($entry->getPrice() > 0 && $entry->getExtraData('refund')) {
                            $time = $entry->getExtraData('refund.createtime');
                            $data[$header] = date('Y-m-d H:i:s', $time);
                        } else {
                            $data[$header] = '';
                        }
                        break;
                    case 'account_title':
                        $account = Account::findOneFromName($entry->getAccount());
                        $title = $account ? $account->getTitle() : $entry->getAccount();
                        $data[$header] = str_replace('"', '', $title);
                        break;
                    case 'agent':
                        $agent = Agent::get($entry->getAgentId());
                        $name = $agent ? $agent->getName() : $entry->getExtraData('agent.name', '');
                        $data[$header] = str_replace('"', '', $name);
                        break;
                    case 'device_title':
                        $data[$header] = $device ? str_replace('"', '', $device->getName()) : '';
                        break;
                    case 'device_imei':
                        $data[$header] = $device ? $device->getImei() : $entry->getExtraData('device.imei', '');
                        break;
                    case 'createtime':
                        $data[$header] = date('Y-m-d H:i:s', $entry->getCreatetime());
                        break;
                    default:
                        $data[$header] = '';
                }
            }
            $result[] = $data;
        }

        $all_headers = Order::getExportHeaders();
        $column = array_values(array_intersect_key($all_headers, array_flip($headers)));

        Util::exportExcelFile($filename, $column, $result);

        return $last_id;
    }
}
