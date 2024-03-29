<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\business\VIP;
use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$vip = VIP::get(Request::int('id'));
if (!$vip) {
    JSON::fail('找不到这个VIP用户！');
}

$devices_list = [];

$ids = $vip->getDeviceIds();

foreach ($ids as $id) {
    $device = Device::get($id);
    if ($device) {
        $data = $device->profile();
        $data['enabled'] = $device->getAgentId() == $vip->getAgentId();
        $devices_list[] = $data;
    }
}

$user = $vip->getUser();

Response::templateJSON(
    'web/agent/vip_devices',
    "{$vip->getName()}的可用设备",
    [
        'vip' => [
            'id' => $vip->getId(),
        ],
        'user' => $user ? $user->profile() : [],
        'list' => $devices_list,
    ]
);