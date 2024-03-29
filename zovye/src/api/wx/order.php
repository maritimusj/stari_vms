<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use zovye\App;
use zovye\business\ChargingNowData;
use zovye\domain\Balance;
use zovye\domain\Device;
use zovye\domain\Goods;
use zovye\domain\User;
use zovye\model\agentModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Helper;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;

class order
{
    /**
     * 代理商和运营人员都要请求这个接口
     */
    public static function detail(userModelObj $user): array
    {
        unset($user);

        $order_id = Request::int('orderid');
        $order = \zovye\domain\Order::get($order_id);
        if (empty($order)) {
            return err('找不到这个订单!');
        }

        $result = [];
        $user = User::get($order->getOpenid(), true);
        if ($user) {
            $result['user'] = [
                'name' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
            ];
        }
        $device = Device::get($order->getDeviceId());
        if ($device) {
            $result['device'] = [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ];
        }
        if ($order->getPrice() > 0) {
            $m = number_format($order->getPrice() / 100, 2);
            $result['spec'] = "支付{$m}元购买";
        } else {
            $result['spec'] = '免费领取';
        }

        $goods_id = $order->getGoodsId();
        if ($goods_id) {
            $result['goods'] = Goods::data($goods_id);
        }

        $group = $order->getExtraData('group');
        if ($group) {
            $result['group'] = $group;
            $result['charging'] = $order->getExtraData('charging', []);
            $result['charging']['chargerID'] = $order->getChargerID();
            if (!$order->isChargingFinished()) {
                $device = $order->getDevice();
                if ($device) {
                    $chargerID = $order->getChargerID();
                    $charging_now_data = ChargingNowData::getByDevice($device, $chargerID);
                    if ($charging_now_data && $charging_now_data->getSerial() == $result['orderId']) {
                        $result['charging']['status'] = $device->getChargerStatusData($chargerID);
                    }
                }
            } else {
                $timeout = $order->getExtraData('timeout', []);
                if ($timeout) {
                    $result['charging']['timeout'] = $timeout;
                }
            }
        }

        $result['createtime'] = date('Y-m-d H:i:s', $order->getCreatetime());

        return $result;
    }

    public static function default(agentModelObj $user): array
    {
        if (Request::has('guid')) {
            $guid = Request::str('guid');
            $user = agent::getUserByGUID($guid);
            if (empty($user)) {
                return err('找不到这个用户！');
            }
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $query = \zovye\domain\Order::query(['agent_id' => $agent->getId()]);

        if (Request::has('deviceid')) {
            $device_id = Request::int('deviceid');
            $device = Device::get($device_id);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
        } elseif (Request::has('device')) {
            $device_uid = Request::str('device');
            $device = Device::get($device_uid, true);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
        }

        if (isset($device)) {
            if ($device->getAgentId() != $agent->getId()) {
                return err('没有权限管理这个设备！');
            }
            $query->where(['device_id' => $device->getId()]);
        }

        if (Request::has('start')) {
            $begin = DateTime::createFromFormat('Y-m-d H:i:s', Request::str('start').' 00:00:00');
            $query->where(['createtime >=' => $begin->getTimestamp()]);
        }

        if (Request::has('end')) {
            $end = DateTime::createFromFormat('Y-m-d H:i:s', Request::str('end').' 00:00:00');
            $end->modify('next day 00:00');
            $query->where(['createtime <' => $end->getTimestamp()]);
        }

        $order_no = Request::trim('order');
        if ($order_no) {
            $query->whereOr([
                'order_id LIKE' => "%$order_no%",
                'extra REGEXP' => "\"transaction_id\":\"[0-9]*{$order_no}[0-9]*\"",
            ]);
            //{$order_no}的括号不能去掉
        }

        $way = Request::trim('way');
        if ($way == 'free') {
            if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
                $query->where([
                    'src' => [\zovye\domain\Order::ACCOUNT, \zovye\domain\Order::FREE, \zovye\domain\Order::BALANCE],
                ]);
            } else {
                $query->where(['src' => [\zovye\domain\Order::ACCOUNT, \zovye\domain\Order::FREE]]);
            }
        } elseif ($way == 'pay') {
            if (App::isBalanceEnabled() && Balance::isPayOrder()) {
                $query->where(['src' => [\zovye\domain\Order::PAY, \zovye\domain\Order::BALANCE]]);
            } else {
                $query->where(['src' => \zovye\domain\Order::PAY]);
            }
        } elseif ($way == 'refund') {
            $query->where(['refund' => 1]);
        }

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $orders = [];

        /** @var orderModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'num' => $entry->getNum(),
                'price' => number_format($entry->getPrice() / 100, 2),
                'account' => $entry->getAccount(),
                'orderId' => $entry->getOrderId(),
                'agentId' => $entry->getAgentId(),
                'from' => User::getUserCharacter($entry->getOpenid()),
                'ip' => $entry->getIp(),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            $data['goods'] = $entry->getExtraData('goods');
            $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

            $group = $entry->getExtraData('group');
            if ($group) {
                $data['charging'] = $entry->getExtraData('charging', []);
                $data['charging']['chargerID'] = $entry->getChargerID();
                if (!$entry->isChargingFinished()) {
                    $device = $entry->getDevice();
                    if ($device) {
                        $chargerID = $entry->getChargerID();
                        $charging_now_data = ChargingNowData::getByDevice($device, $chargerID);
                        if ($charging_now_data && $charging_now_data->getSerial() == $data['orderId']) {
                            $data['charging']['status'] = $device->getChargerStatusData($chargerID);
                        }
                    }
                } else {
                    $timeout = $entry->getExtraData('timeout', []);
                    if ($timeout) {
                        $data['charging']['timeout'] = $timeout;
                    }
                }
            }

            //用户信息
            $user_obj = $entry->getUser();
            if ($user_obj) {
                $data['user'] = [
                    'id' => $user_obj->getId(),
                    'nickname' => $user_obj->getNickname(),
                    'avatar' => $user_obj->getAvatar(),
                ];
            }

            //设备信息
            $device_obj = $entry->getDevice();
            if ($device_obj) {
                $data['device'] = [
                    'name' => $device_obj->getName(),
                    'id' => $device_obj->getId(),
                ];
            }

            //代理商信息
            $agent_obj = $entry->getAgent();
            if ($agent_obj) {
                $data['agentId'] = $agent_obj->getId();
                $data['agent'] = [
                    'name' => $agent_obj->getName(),
                    'avatar' => $agent_obj->getAvatar(),
                    'level' => $agent_obj->getAgentLevel(),
                ];
            }

            $account_obj = $entry->getAccount(true);
            if ($account_obj) {
                $data['accountId'] = $account_obj->getId();
                $data['account'] = $account_obj->profile();
            }

            if ($entry->isRefund() && $entry->getExtraData('refund')) {
                $time = $entry->getExtraData('refund.createtime');
                $time_formatted = date('Y-m-d H:i:s', $time);
                $data['refund'] = [
                    'title' => "退款时间：$time_formatted",
                    'reason' => $entry->getExtraData('refund.message'),
                ];
                $data['clr'] = '#ccc';
            }

            $pay_result = $entry->getExtraData('payResult');
            $data['transaction_id'] = $pay_result['transaction_id'] ?? ($pay_result['uniontid'] ?? '');

            //出货结果
            $data['result'] = $entry->getExtraData('pull.result', []);

            if (is_error($data['result'])) {
                $data['status'] = [
                    'title' => $data['result']['message'],
                    'clr' => '#F56C6C',
                ];
            } else {
                $data['status'] = [
                    'title' => '出货成功',
                    'clr' => '#67C23A',
                ];
            }

            if ($entry->isChargingOrder()) {
                $data['type'] = 'charging';
                $data['pay'] = $entry->getExtraData('card', []);
                $refund = $entry->getExtraData('charging.refund', []);
                if ($refund) {
                    $data['pay']['refund'] = $refund;
                }
            } elseif ($entry->isFuelingOrder()) {
                $data['type'] = 'fueling';
                $data['pay'] = $entry->getExtraData('card', []);
                $refund = $entry->getExtraData('fueling.refund', []);
                if ($refund) {
                    $data['pay']['refund'] = $refund;
                }
            } else {
                $data['type'] = 'normal';
            }

            $orders[] = $data;
        }

        return [
            'orders' => $orders,
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
        ];
    }

    public static function getOrderExportHeaders(): array
    {
        $headers = \zovye\domain\Order::getExportHeaders();
        unset($headers['ID']);

        return $headers;
    }

    public static function orderExportDo(agentModelObj $agent): array
    {
        $params = [
            'agent_openid' => $agent->getOpenid(),
            'account_id' => Request::int('account_id'),
            'device_uid' => Request::str('device_uid'),
            'start' => Request::str('start'),
            'end' => Request::str('end'),
        ];

        $query = \zovye\domain\Order::getExportQuery($params);
        if (is_error($query)) {
            return $query;
        }

        $step = Request::str('step');
        if (empty($step) || $step == 'init') {
            return [
                'total' => $query->count(),
                'serial' => $agent->getId().(new DateTime())->format('YmdHis'),
            ];
        } else {
            $serial = Request::str('serial');
            if (empty($serial)) {
                return err("缺少serial");
            }

            $filename = "$serial.csv";
            $dirname = "export/order/";
            $full_filename = Helper::getAttachmentFileName($dirname, $filename);

            if ($step == 'load') {
                $query = $query->where(['id >' => Request::int('last')])->limit(100)->orderBy('id ASC');
                $last_id = \zovye\domain\Order::export($full_filename, $query, Request::array('headers'));

                return [
                    'num' => 100,
                    'last' => $last_id,
                ];

            } elseif ($step == 'download') {
                return [
                    'url' => str_replace('/addons/'.APP_NAME, '', Util::toMedia("$dirname$filename")),
                ];
            }
        }

        return err('不正确的请求！');
    }
}