<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;

$user = User::get(Request::int('user'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$keeper = $user->getKeeper();
if (empty($keeper)) {
    JSON::fail('这个用户不是运营人员！');
}

/** @var deviceModelObj $entry */
$device = Device::query([
    'keeper_id' => $keeper->getId(),
    'id' => Request::int('id'),
])->findOne();

if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if ($device->getCommissionFixed() != -1) {
    $commission_val = number_format(abs($device->getCommissionFixed()) / 100, 2);
    $commission_type = 'fixed';
} else {
    $commission_val = number_format($device->getCommissionPercent() / 100, 2);
    $commission_type = 'percent';
}

$content = app()->fetchTemplate(
    'web/user/keeper_device_edit',
    [
        'device' => $device->profile(),
        'val' => $commission_val,
        'type' => $commission_type,
        'kind' => $device->getKind(),
        'way' => $device->getWay(),
    ]
);

JSON::success(['title' => "设备佣金[ {$device->getName()} ]" , 'content' => $content]);