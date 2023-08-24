<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\chargingTimeout;

defined('IN_IA') or exit('Access Denied');

use zovye\Charging;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Job;
use zovye\JobException;
use zovye\Log;

use zovye\model\orderModelObj;
use zovye\Order;
use zovye\Pay;
use zovye\Request;

$uid = Request::str('uid');
$charger_id = Request::int('chargerID');
$device_id = Request::int('device');
$user_id = Request::int('user');
$order_id = Request::int('order');
$time = Request::int('time');

$log = [
    'uid' => $uid,
    'chargerID' => $charger_id,
    'device' => $device_id,
    'user' => $user_id,
    'order' => $order_id,
    'time' => $time,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

/** @var orderModelObj $order */
$order = Order::get($uid, true);
if ($order) {
    $result = $order->getChargingResult();
    if (empty($result)) {
        Charging::end($uid, $charger_id, function ($order) {
            $order->setExtraData('timeout', [
                'at' => time(),
                'reason' => '充电桩无响应，请稍后再试！',
            ]);
            //如果即时支付，尝试退款
            $pay_log = Pay::getPayLog($order->getOrderNO());
            if ($pay_log) {
                Job::refund($order->getOrderNO(), '充电订单超时退款');
            }
        });

        $log['error'] = [
            'at' => time(),
            'reason' => '充电桩无响应，请稍后再试！',
        ];
    } else {
        $log['result'] = $result;
    }
}

$device = Device::get($device_id);
if ($device) {
    $data = $device->getChargerBMSData($charger_id);
    if (empty($data)) {
        Charging::end($uid, $charger_id, function ($order) {
            $order->setExtraData('timeout', [
                'at' => time(),
                'reason' => '充电桩失去响应，请重试！',
            ]);
            //如果即时支付，尝试退款
            $pay_log = Pay::getPayLog($order->getOrderNO());
            if ($pay_log) {
                Job::refund($order->getOrderNO(), '充电订单超时退款');
            }
        });
        $log['error'] = [
            'at' => time(),
            'reason' => '充电桩失去响应，请重试！',
        ];
    } else {
        $log['BMS'] = $data;
    }
}

$log['time_formatted'] = date('Y-m-d H:i:s', $log['time']);
Log::debug('charging_start_timeout', $log);