<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (!App::isDeviceWithDoorEnabled()) {
    JSON::fail('没有启用这个功能！');
}

$id = request::int('id');
$device = Device::get($id);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$index = request::int('index', 1);

$result = $device->openDoor($index);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success('开锁指令已发送！');