<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$user = User::get(request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$data = $user->chargingNOWData();
if (empty($data)) {
    JSON::fail('没有发现用户充电信息！');
}

$device = Device::get($data['device']);
if (empty($device)) {
    $user->removeChargingNOWData();
    JSON::success('找不到设备，已清除用户充电状态！');
}

$deviceNOW = $device->chargingNOWData($data['chargerID']);
if (empty($deviceNOW) || $deviceNOW['serial'] != $data['serial']) {
    $user->removeChargingNOWData();
    JSON::success('设备状态不匹配，已清除用户充电状态！');
}

$order = Order::get($data['serial'], true);
if (empty($order) || $order->isChargingFinished()) {
    $user->removeChargingNOWData();
    $device->removeChargingNOWData($data['chargerID']);
    JSON::success('充电订单已结束，已清相关充电状态！');
}

// 暂时不做后续处理
// $content = app()->fetchTemplate(
//     'web/user/charging',
//     [
//         'user' => $user->profile(),
//         'device' => $device->profile(),
//         'order' => $order->profile(),
//         'status' => $device->getChargerStatusData($data['chargerID']),
//     ]
// );

// JSON::success(['title' => '充电信息', 'content' => $content]);