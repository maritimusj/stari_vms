<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\fuelingTimeout;

use zovye\CtrlServ;
use zovye\Device;
use zovye\Fueling;
use zovye\Log;

use zovye\Order;
use zovye\request;

$uid = request::str('uid');
$chargerID = request::int('chargerID');
$device_id = request::int('device');
$user_id = request::int('user');
$order_id = request::int('order');
$time = request::int('time');

$params = [
    'uid' => $uid,
    'chargerID' => $chargerID,
    'device' => $device_id,
    'user' => $user_id,
    'order' => $order_id,
    'time' => $time,
];

$op = request::op('default');
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