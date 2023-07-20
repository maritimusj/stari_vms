<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\fuelingTimeout;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\Device;
use zovye\Fueling;
use zovye\Log;

use zovye\Order;
use zovye\Request;

$uid = Request::str('uid');
$chargerID = Request::int('chargerID');
$device_id = Request::int('device');
$user_id = Request::int('user');
$order_id = Request::int('order');
$time = Request::int('time');

$params = [
    'uid' => $uid,
    'chargerID' => $chargerID,
    'device' => $device_id,
    'user' => $user_id,
    'order' => $order_id,
    'time' => $time,
];

$op = Request::op('default');
if ($op == 'fueling_start_timeout' && CtrlServ::checkJobSign($params)) {
    $order = Order::get($uid, true);
    if ($order) {
        $result = $order->getFuelingResult();
        if (empty($result)) {
            Fueling::end($uid, $chargerID, function ($order) {
                $order->setSrc(Order::FUELING);

                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '设备无响应，请稍后再试！',
                ]);
            });

            $params['error'] = [
                'at' => time(),
                'reason' => '设备无响应，请稍后再试！',
            ];
        } else {
            $params['result'] = $result;
        }
    }

    $device = Device::get($device_id);
    if ($device) {
        $data = $device->getFuelingStatusData($chargerID);
        if (empty($data)) {
            Fueling::end($uid, $chargerID, function ($order) {
                $order->setSrc(Order::FUELING);

                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '设备失去响应，请重试！',
                ]);
            });
            $params['error'] = [
                'at' => time(),
                'reason' => '设备失去响应，请重试！',
            ];
        } else {
            $params['status'] = $data;
        }
    }
}

$params['time_formatted'] = date('Y-m-d H:i:s', $params['time']);
Log::debug('fueling_start_timeout', $params);