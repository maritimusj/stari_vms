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
            $data['group'] = $group;
            $data['charging'] = $order->getExtraData('charging', []);
            $data['charging']['chargerID'] = $order->getChargerID();
            if (!$order->isChargingFinished()) {
                $device = $order->getDevice();
                if ($device) {
                    $chargerID = $order->getChargerID();
                    if ($device->settings("chargingNOW.$chargerID.serial") == $data['orderId']) {
                        $data['charging']['status'] = $device->getChargerData($chargerID);
                    }
                }
            } else {
                $timeout = $order->getExtraData('timeout', []);
                if ($timeout) {
                    $data['charging']['timeout'] = $timeout;
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
            $user = common::getAgent();
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
                        if ($device->settings("chargingNOW.$chargerID.serial") == $data['orderId']) {
                            $data['charging']['status'] = $device->getChargerData($chargerID);
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
            $orders[] = $data;
        }

        return [
            'orders' => $orders,
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total ?? 0,
        ];
    }

    public static function getExportIds(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user->Agent() : $user->getPartnerAgent();

        return \zovye\Order::getExportIDS([
            'agent_openid' => $agent->getOpenid(),
            'account_id' => request::int('accountId'),
            'device_id' => request::str('deviceId'),//deviceId is imei
            'last_id' => request::int('lastId'),
            'start' => request::str('start'),
            'end' => request::str('end'),
        ]);
    }

    public static function export(): array
    {
        return \zovye\Order::export([
            'headers' => request::array('headers', \zovye\Order::getExportHeaders(true)),
            'uid' => request::trim('uid'),
            'ids' => request::array('ids'),
        ]);
    }
}