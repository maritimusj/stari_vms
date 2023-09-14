<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\fuelingTimeout;

defined('IN_IA') or exit('Access Denied');

use zovye\business\Fueling;
use zovye\CtrlServ;
use zovye\domain\Device;
use zovye\domain\Order;
use zovye\JobException;
use zovye\Log;
use zovye\model\orderModelObj;
use zovye\Request;

$uid = Request::str('uid');
$chargerID = Request::int('chargerID');
$device_id = Request::int('device');
$user_id = Request::int('user');
$order_id = Request::int('order');
$time = Request::int('time');

$log = [
    'uid' => $uid,
    'chargerID' => $chargerID,
    'device' => $device_id,
    'user' => $user_id,
    'order' => $order_id,
    'time' => $time,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$order = Order::get($uid, true);
if ($order) {
    $result = $order->getFuelingResult();
    if (empty($result)) {

        handle($order);

        $log['error'] = [
            'at' => time(),
            'reason' => '设备无响应，请稍后再试！',
        ];
    } else {
        $log['result'] = $result;
    }
}

$device = Device::get($device_id);
if ($device) {
    $data = $device->getFuelingStatusData($chargerID);
    if (empty($data)) {

        handle($order);

        $log['error'] = [
            'at' => time(),
            'reason' => '设备失去响应，请重试！',
        ];
    } else {
        $log['status'] = $data;
    }
}

$log['time_formatted'] = date('Y-m-d H:i:s', $log['time']);
Log::debug('fueling_start_timeout', $log);

function handle(orderModelObj $order) {

    $device = $order->getDevice();
    $chargerID = $order->getChargerID();

    //启动超时，设备也可能已经工作并产生费用

    Fueling::settle($device, [
        'ser' => $order->getOrderNO(),
        'ch' => $chargerID,
        'reason' => -1,
        'solo' => Fueling::MODE_REMOTE,
        'time' => time(),
    ]);

    $order->setExtraData('timeout', [
        'at' => time(),
        'reason' => '设备无响应，请稍后再试！',
    ]);

    $order->save();
}