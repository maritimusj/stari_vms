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

/** @var deviceModelObj $device */
$device = Device::query([
    'keeper_id' => $keeper->getId(),
    'id' => Request::int('id'),
])->findOne();

if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$data = [
    'kind' => Request::int('kind'),
    'way' => Request::int('way'),
    'type' =>  Request::str('type', 'fixed'),
];

if (App::isKeeperCommissionOrderDistinguishEnabled() && $data['way'] == Keeper::COMMISSION_ORDER) {
    $pay_commission_val = Request::float('pay_val', 0, 2);
    $free_commission_val = Request::float('free_val', 0, 2);
    
    if ($data['type'] == 'fixed') {
        $data['fixed'] = max(0, intval($pay_commission_val * 100));
        $data['free_fixed'] = max(0, intval($free_commission_val * 100));
    } else {
        $data['percent'] = max(0, min(10000, intval($pay_commission_val * 100)));
        $data['free_percent'] = max(0, min(10000, intval($free_commission_val * 100)));
    }
} else {
    $commission_val = Request::float('val', 0, 2);

    if ($data['type'] == 'fixed') {
        $data['fixed'] = max(0, intval($commission_val * 100));
    } else {
        $data['percent'] = max(0, min(10000, intval($commission_val * 100)));
    }
}

$device->setKeeper($keeper, $data);

$result = [
    'msg' => '保存成功！',
    'way' => $data['way'],
    'kind' => $data['kind'],
    'type' => $data['type'],
];

if (App::isKeeperCommissionOrderDistinguishEnabled() && $data['way'] == Keeper::COMMISSION_ORDER) {
    $result['pay_val'] = $data['type'] == 'fixed' ? number_format($data['fixed'] / 100, 2, '.', '') . '元' : number_format($data['percent'] / 100, 2, '.', '') . '%';
    $result['free_val'] = $data['type'] == 'fixed' ? number_format($data['free_fixed'] / 100, 2, '.', '') . '元' : number_format($data['free_percent'] / 100, 2, '.', '') . '%';
} else {
    $result['val'] = $data['type'] == 'fixed' ? number_format($data['fixed'] / 100, 2, '.', '') . '元' : number_format($data['percent'] / 100, 2, '.', '') . '%';
}

JSON::success($result);