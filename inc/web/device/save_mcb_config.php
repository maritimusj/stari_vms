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

$data = urldecode(base64_decode(Request::str('data', '')));

if ($data) {
    $json = json_decode($data, true);
    if (!$json) {
        JSON::fail('请检查配置内容是不是有效的JSON格式！');
    }
    $device->updateSettings('extra.mcb_config', $json);
} else {
    $device->removeSettings('extra', 'mcb_config');
}

JSON::success('成功！');