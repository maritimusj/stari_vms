<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\chargingTimeout;

use zovye\Charging;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Job;
use zovye\Log;

use zovye\model\orderModelObj;
use zovye\Order;
use zovye\Pay;
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
if ($op == 'charging_start_timeout' && CtrlServ::checkJobSign($params)) {
    /** @var orderModelObj $order */
    $order = Order::get($uid, true);
    if ($order) {
        $result = $order->getChargingResult();
        if (empty($result)) {
            Charging::end($uid, $chargerID, function ($order) {
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

            $params['error'] = [
                'at' => time(),
                'reason' => '充电桩无响应，请稍后再试！',
            ];
        } else {
            $params['result'] = $result;
        }
    }

    $device = Device::get($device_id);
    if ($device) {
        $data = $device->getChargerBMSData($chargerID);
        if (empty($data)) {
            Charging::end($uid, $chargerID, function ($order) {
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
            $params['error'] = [
                'at' => time(),
                'reason' => '充电桩失去响应，请重试！',
            ];
        } else {
            $params['BMS'] = $data;
        }
    }
}

$params['time_formatted'] = date('Y-m-d H:i:s', $params['time']);
Log::debug('charging_start_timeout', $params);