<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use zovye\App;
use zovye\Balance;
use zovye\Device;
use zovye\Goods;
use zovye\request;
use zovye\model\orderModelObj;
use zovye\State;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\error;
use function zovye\is_error;

class order
{
    public static function detail(): array
    {
        $order_id = request::int('orderid');
        $order = \zovye\Order::get($order_id);
        if (empty($order)) {
            return error(State::ERROR, '找不到这个订单!');
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
            $result['spec'] = "支付￥{$m}元购买";
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
                    if ($device->chargingNOWData($chargerID, 'serial', '') == $result['orderId']) {
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

    public static function default(): array
    {
        if (request::has('guid')) {
            $guid = request::str('guid');
            $user = agent::getUserByGUID($guid);
            if (empty($user)) {
                return err('找不到这个用户！');
            }
        } else {
            $user = common::getAgentOrPartner();
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $query = \zovye\Order::query(['agent_id' => $agent->getId()]);

        if (request::has('deviceid')) {
            $device_id = request::int('deviceid');
            $device = Device::get($device_id);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
        } elseif (request::has('device')) {
            $device_uid = request::str('device');
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

        if (request::has('start')) {
            $begin = DateTime::createFromFormat('Y-m-d H:i:s', request::str('start').' 00:00:00');
            $query->where(['createtime >=' => $begin->getTimestamp()]);
        }

        if (request::has('end')) {
            $end = DateTime::createFromFormat('Y-m-d H:i:s', request::str('end').' 00:00:00');
            $end->modify('next day 00:00');
            $query->where(['createtime <' => $end->getTimestamp()]);
        }

        $order_no = request::trim('order');
        if ($order_no) {
            $query->whereOr([
                'order_id LIKE' => "%$order_no%",
                'extra REGEXP' => "\"transaction_id\":\"[0-9]*{$order_no}[0-9]*\"",
            ]);
            //{$order_no}的括号不能去掉
        }

        $way = request::trim('way');
        if ($way == 'free') {
            if (App::isBalanceEnabled() && Balance::isFreeOrder()) {
                $query->where([
                    'src' => [\zovye\Order::ACCOUNT, \zovye\Order::FREE, \zovye\Order::BALANCE],
                ]);
            } else {
                $query->where(['src' => [\zovye\Order::ACCOUNT, \zovye\Order::FREE]]);
            }
        } elseif ($way == 'pay') {
            if (App::isBalanceEnabled() && Balance::isPayOrder()) {
                $query->where(['src' => [\zovye\Order::PAY, \zovye\Order::BALANCE]]);
            } else {
                $query->where(['src' => \zovye\Order::PAY]);
            }
        } elseif ($way == 'refund') {
            $query->where(['refund' => 1]);
        }

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

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
                        if ($device->chargingNOWData($chargerID, 'serial', '') == $data['orderId']) {
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

            if ($entry->isChargingOrder()) {
                $data['type'] = 'charging';
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
            'total' => $total ?? 0,
        ];
    }

    public static function getOrderExportHeaders(): array
    {
        $headers = \zovye\Order::getExportHeaders();
        unset($headers['ID']);

        return $headers;
    }

    public static function orderExportDo(): array
    {
        $agent = common::getAgent();

        $params = [
            'agent_openid' => $agent->getOpenid(),
            'account_id' => request::int('account_id'),
            'device_uid' => request::str('device_uid'),
            'start' => request::str('start'),
            'end' => request::str('end'),
        ];

        $query = \zovye\Order::getExportQuery($params);
        if (is_error($query)) {
            return $query;
        }

        $step = request::str('step');
        if (empty($step) || $step == 'init') {
            return [
                'total' => $query->count(),
                'serial' => $agent->getId() . (new DateTime())->format('YmdHis'),
            ];
        } else {
            $serial = request::str('serial');
            if (empty($serial)) {
                return err("缺少serial");
            }

            $filename = "$serial.csv";
            $dirname = "export/order/";
            $full_filename = Util::getAttachmentFileName($dirname, $filename);

            if ($step == 'load') {
                $query = $query->where(['id >' => request::int('last')])->limit(100)->orderBy('id asc');
                $last_id = \zovye\Order::export($full_filename, $query, request::array('headers'));

                return [
                    'num' => 100,
                    'last' => $last_id,
                ];

            } elseif ($step == 'download') {
                return [
                    'url' => Util::toMedia("$dirname$filename"),
                ];
            }            
        }

        return err('不正确的请求！');
    }
}