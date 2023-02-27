<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$type_id = request::int('id');
$device_type = DeviceTypes::get($type_id);
if (empty($device_type)) {
    JSON::fail('找不到这个设备型号！');
}

$firstType = settings('device.multi-types.first');
if ($firstType == $type_id) {
    if (updateSettings('device.multi-types.first', 0)) {
        JSON::success(['msg' => '设置成功！', 'typeid' => 0]);
    }
} else {
    if (updateSettings('device.multi-types.first', $type_id)) {
        JSON::success(['msg' => '设置成功！', 'typeid' => $type_id]);
    }
}

JSON::fail('保存失败！');