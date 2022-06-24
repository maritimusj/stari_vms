<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\chargingTimeout;

use zovye\Charging;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;

use zovye\Order;
use zovye\request;


$serial = request::str('serial');
$chargerID = request::int('chargerID');
$device_id = request::int('device');
$user_id = request::int('user');
$order_id = request::int('order');
$time = request::int('time');

$params = [
    'serial' => $serial,
    'chargerID' => $chargerID,
    'device' => $device_id,
    'user' => $user_id,
    'order' => $order_id,
    'time' => $time,
];

$op = request::op('default');
if ($op == 'charging_timeout' && CtrlServ::checkJobSign($params)) {
    $order = Order::get($serial, true);
    if ($order) {
        $result = $order->getChargingResult();
        if (empty($result)) {
            Charging::end($serial, $chargerID, function ($order) {
                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '没有收到充电结果通知！',
                ]);
            });

            $params['error'] = [
                'at' => time(),
                'reason' => '没有收到充电结果通知！',
            ];
        }
    }

    $device = Device::get($device_id);
    if ($device) {
        $data = $device->getChargerBMSData($chargerID);
        if (empty($data)) {
            Charging::end($serial, $chargerID, function ($order) {
                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '没有收到充电设备消息反馈！',
                ]);
            });
            $params['error'] = [
                'at' => time(),
                'reason' => '没有收到充电设备消息反馈！',
            ];
        }
    }
}

Log::debug('charging_timeout', $params);