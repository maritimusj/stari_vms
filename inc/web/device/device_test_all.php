<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if ($device->isChargingDevice()) {
    $title = "设备 [ {$device->getName()} ]";
    $tpl = 'web/device/charger_detail';
    $data = [
        'device_id' => $device->getId(),
    ];
} else {
    $title = "设备 [ {$device->getName()} ]";
    $tpl = 'web/device/cargo_lanes_test';
    $data = [
        'device_id' => $device->getId(),
        'is_fueling_device' => $device->isFuelingDevice(),
        'is_bluetooth_device' => $device->isBlueToothDevice(),
        'params' => $device->getPayload(true),
    ];
}

Response::templateJSON($tpl, $title, $data);