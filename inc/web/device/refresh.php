<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$device = Device::get(request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if (Device::refresh($device)) {
    JSON::success('设备状态已刷新！');
}

JSON::fail('设备状态刷新失败！');