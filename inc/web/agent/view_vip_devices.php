<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$vip = VIP::get(request::int('id'));
if (!$vip) {
    JSON::fail('找不到这个VIP用户！');
}

$devices_list = [];

$ids = $vip->getDeviceIds();

foreach($ids as $id) {
    $device = Device::get($id);
    if ($device) {
        $data = $device->profile();
        $data['enabled'] = $device->getAgentId() == $vip->getAgentId();
        $devices_list[] = $data;
    }
}
$content = app()->fetchTemplate(
    'web/agent/vip_devices',
    [
        'vip' => [
            'id' => $vip->getId(),
        ],
        'user' => $vip->getUser() ?? [],
        'list' => $devices_list,
    ]
);

JSON::success(['title' => "{$vip->getName()}的可用设备", 'content' => $content]);