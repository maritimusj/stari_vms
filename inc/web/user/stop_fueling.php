<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$data = $user->fuelingNOWData();
if (empty($data)) {
    JSON::fail('没有发现用户加注信息！');
}

$device = Device::get($data['device']);
if (empty($device)) {
    $user->removeFuelingNOWData();
    JSON::success('找不到设备，已清除用户加注状态！');
}

$deviceNOW = $device->fuelingNOWData($data['chargerID']);
if (empty($deviceNOW) || $deviceNOW['serial'] != $data['serial']) {
    $user->removeFuelingNOWData();
    JSON::success('设备状态不匹配，已清除用户加注状态！');
}

$order = Order::get($data['serial'], true);
if (empty($order) || $order->isFuelingFinished()) {
    $user->removeFuelingNOWData();
    $device->removeFuelingNOWData($data['chargerID']);
    JSON::success('订单已结束，已清相关加注状态！');
}

// 暂时不做后续处理
//Response::fetchTemplate(
//    'web/user/feuling',
//    '加注信息',
//    [
//        'user' => $user->profile(),
//        'device' => $device->profile(),
//        'order' => $order->profile(),
//        'status' => $device->getFuelingStatusData($data['chargerID']),
//    ]
//);