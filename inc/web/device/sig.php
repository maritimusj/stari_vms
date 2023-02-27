<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$device = Device::get(request::int('id'));
if ($device) {
    JSON::success([
        'sig' => "{$device->getSig()}%",
    ]);
}

JSON::fail('找不到这个设备');