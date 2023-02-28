<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\packageModelObj;

$device_id = request::int('device');
if ($device_id) {
    $device = Device::get($device_id);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }
}

$query = Package::query(['device_id' => $device_id]);
$query->orderBy('id ASC');

$result = [];

/** @var packageModelObj $entry */
foreach ($query->findAll() as $entry) {
    $result[] = $entry->format(true);
}

JSON::success($result);