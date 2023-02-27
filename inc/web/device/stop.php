<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$device = Device::get($id);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$chargerID = request::int('chargerID');

$params = [
    "req" => "stop",
    "ch" => $chargerID,
];

$device->mcbNotify('config', '', $params);