<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Device;
use zovye\domain\Keeper;
use zovye\domain\User;
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

$data = [
    'device' => $device->profile(),
    'kind' => $device->getKind(),
    'way' => $device->getWay(),
];

if (App::isKeeperCommissionOrderDistinguishEnabled() && $device->getWay() == Keeper::COMMISSION_ORDER) {
    if ($device->getCommissionFixed() != -1) {
        $data['pay_val'] = number_format(abs($device->getCommissionFixed()) / 100, 2, '.', '');
        $data['free_val'] = number_format(abs($device->getCommissionFreeFixed()) / 100, 2, '.', '');
        $data['type'] = 'fixed';
    } else {
        $data['pay_val'] = number_format($device->getCommissionPercent() / 100, 2, '.', '');
        $data['free_val'] = number_format($device->getCommissionFreePercent() / 100, 2, '.', '');
        $data['type'] = 'percent';
    }
} else {
    if ($device->getCommissionFixed() != -1) {
        $data['val'] = number_format(abs($device->getCommissionFixed()) / 100, 2, '.', '');
        $data['type'] = 'fixed';
    } else {
        $data['val'] = number_format($device->getCommissionPercent() / 100, 2, '.', '');
        $data['type'] = 'percent';
    }
}

Response::templateJSON(
    'web/user/keeper_device_edit',
    "设备佣金[ {$device->getName()} ]",
    $data
);