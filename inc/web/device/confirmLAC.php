<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$device = Device::get($id);
if ($device && $device->confirmLAC()) {
    JSON::success('已确认！');
}

JSON::fail('找不到这个设备！');