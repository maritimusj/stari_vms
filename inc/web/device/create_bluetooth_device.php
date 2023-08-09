<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$data = [
    'agent_id' => Request::int('agent'),
    'name' => Request::trim('name'),
    'imei' => Request::trim('imei'),
    'group_id' => Request::int('groupId'),
    'capacity' => 0,
    'remain' => 0,
];

if (empty($data['name']) || empty($data['imei'])) {
    JSON::fail('设备名称或IMEI不能为空！');
}

if (!Locker::try('create:'.$data['imei'])) {
    JSON::fail('无法锁定，请稍后重试！');
}

if (Device::get($data['imei'], true)) {
    JSON::fail('IMEI已经存在！');
}

$extra = [
    'pushAccountMsg' => '',
    'activeQrcode' => 0,
    'grantloc' => [
        'lng' => 0,
        'lat' => 0,
    ],
    'location' => [],
];

$protocol = strtolower(Request::trim('protocol'));
if (empty($protocol)) {
    $protocol = 'wx';
}

$blue_tooth_screen = Request::bool('screen') ? 0 : 1;
$power = Request::bool('power') ? 0 : 1;
$blue_tooth_disinfectant = Request::bool('disinfect') ? 0 : 1;

$extra['bluetooth'] = [
    'protocol' => $protocol,
    'uid' => Request::trim('buid'),
    'mac' => Request::trim('mac'),
    'screen' => $blue_tooth_screen,
    'power' => $power,
    'disinfectant' => $blue_tooth_disinfectant,
];

if ($data['agent_id']) {
    $agent = Agent::get($data['agent_id']);
    if (empty($agent)) {
        JSON::fail('找不到这个代理商!');
    }
}

$type_id = Request::int('typeid');
if ($type_id) {
    $device_type = DeviceTypes::get($type_id);
    if (empty($device_type)) {
        JSON::fail('设备类型不正确!');
    }

    $type_data = DeviceTypes::format($device_type);

    $data['device_type'] = $type_data['id'];
    $extra['cargo_lanes'] = $type_data['cargo_lanes'];
} else {
    $defaultType = DeviceTypes::getDefault();
    if ($defaultType) {
        $data['device_type'] = $defaultType->getId();
    } else {
        $data['device_type'] = 0;
        $extra['cargo_lanes'] = [];
    }
}

$device = Device::create($data);

if (empty($device)) {
    JSON::fail('创建失败！');
}

$device->setDeviceModel(Device::BLUETOOTH_DEVICE);
$device->updateQrcode(true);

if ($device->set('extra', $extra) && $device->save()) {
    JSON::success(['message' => '成功']);
}

JSON::fail('无法保存数据！');