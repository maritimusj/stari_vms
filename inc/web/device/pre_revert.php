<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(request('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$content = app()->fetchTemplate(
    'web/device/confirm',
    [
        'device_id' => $device->getId(),
        'confirm_code' => $device->getShadowId(),
        'device_name' => $device->getName(),
    ]
);

JSON::success(['title' => '注意：重置会删除该设备的所有设置及订单记录，并且无法恢复！', 'content' => $content]);