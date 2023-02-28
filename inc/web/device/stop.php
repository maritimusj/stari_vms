<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$device = Device::get($id);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$chargerID = Request::int('chargerID');

$params = [
    "req" => "stop",
    "ch" => $chargerID,
];

$device->mcbNotify('config', '', $params);