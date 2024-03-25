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
use zovye\model\keeper_devicesModelObj;

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$keeper = $user->getKeeper();
if (empty($keeper)) {
    JSON::fail('这个用户不是运营人员！');
}

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Device::query(['keeper_id' => $keeper->getId()]);

$total = $query->count();
$pager = We7::pagination($total, $page, $page_size);

$query->orderBy('createtime DESC');
$query->page($page, $page_size);

$list = [];
/** @var keeper_devicesModelObj $item */
foreach ($query->findAll() as $item) {
    $data = [
        'id' => $item->getId(),
        'name' => $item->getName(),
        'imei' => $item->getImei(),
    ];
    if (App::isKeeperCommissionOrderDistinguishEnabled() && $item->getWay() == Keeper::COMMISSION_ORDER) {
        if ($item->isFixedValue()) {
            $data['pay_val'] = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
            $data['free_val'] = number_format(abs($item->getCommissionFreeFixed()) / 100, 2).'元';
        } else {
            $data['pay_val'] = number_format($item->getCommissionPercent() / 100, 2).'%';
            $data['free_val'] = number_format($item->getCommissionFreePercent() / 100, 2).'%';
        }
    } else {
        if ($item->isFixedValue()) {
            $data['val'] = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
        } else {
            $data['val'] = number_format($item->getCommissionPercent() / 100, 2).'%';
        }
    }

    $data['way'] = $item->getWay();
    $data['kind'] = $item->getKind();

    if (App::isAppOnlineBonusEnabled()) {
        $percent = $item->getAppOnlineBonusPercent();
        $data['app_online_bonus_percent'] = $percent > 0 ? number_format($percent / 100, 2, '.', '') . '%' : '';
    }

    if (App::isDeviceQoeBonusEnabled()) {
        $percent = $item->getDeviceQoeBonusPercent();
        $data['device_qoe_bonus_percent'] = $percent > 0 ? number_format($percent / 100, 2, '.', '') . '%' : '';
    }

    $list[] = $data;
}

Response::showTemplate(
    'web/user/keeper_device',
    [
        'keeper' => $keeper,
        'user' => $user,
        'devices' => $list,
        'pager' => $pager,
    ]
);