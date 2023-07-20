<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$tpl_data = [
    'config' => $device->settings('schedule', []),
    'device' => $device,
    'payload' => $device->getPayload(true),
];

Response::templateJSON('web/device/schedule', "定时出货 [ {$device->getName()} ]",  $tpl_data);