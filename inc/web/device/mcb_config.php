<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Device;

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$data = $device->settings('extra.mcb_config', []);

Response::templateJSON(
    'web/device/mcb_config',
    '主板配置',
    [
        'device' => $device->profile(),
        'data' => $data ? json_encode($data) : '',
    ]
);
