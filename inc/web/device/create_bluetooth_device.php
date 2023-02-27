<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$data = [
    'agent_id' => request('agent'),
    'name' => request('name'),
    'imei' => request('imei'),
    'group_id' => request('groupId'),
    'capacity' => 0,
    'remain' => 0,
];

if (empty($data['name']) || empty($data['imei'])) {
    JSON::fail('设备名称或IMEI不能为空！');
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

$protocol = strtolower(request('protocol'));
if (!in_array($protocol, ['wx', 'grid'])) {
    $protocol = 'wx';
}

$blue_tooth_screen = empty(request('screen')) ? 0 : 1;
$power = empty(request('power')) ? 0 : 1;
$blue_tooth_disinfectant = empty(request('disinfect')) ? 0 : 1;

$extra['bluetooth'] = [
    'protocol' => $protocol,
    'uid' => strval(request('buid')),
    'mac' => strval(request('mac')),
    'screen' => $blue_tooth_screen,
    'power' => $power,
    'disinfectant' => $blue_tooth_disinfectant,
];

if ($data['agent_id']) {
    $agent = Agent::get($data['agentId']);
    if (empty($agent)) {
        JSON::fail('找不到这个代理商!');
    }
}

$type_id = request('typeid');
$device_type = DeviceTypes::get($type_id);
if (empty($device_type)) {
    JSON::fail('设备类型不正确!');
}

$type_data = DeviceTypes::format($device_type);

$data['device_type'] = $type_data['id'];
$extra['cargo_lanes'] = $type_data['cargo_lanes'];

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