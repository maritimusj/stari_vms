<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if ($device) {
    JSON::success([
        'sig' => "{$device->getSig()}%",
    ]);
}

JSON::fail('找不到这个设备');