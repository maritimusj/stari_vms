<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

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

    if ($item->getCommissionFixed() != -1) {
        $commission_val = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
    } else {
        $commission_val = $item->getCommissionPercent().'%';
    }

    $data['val'] = $commission_val;
    $data['way'] = empty($item->getWay()) ? '销售分成' : '补货分成';
    $data['kind'] = $item->getKind();

    $list[] = $data;
}

app()->showTemplate(
    'web/user/keeper_device',
    [
        'keeper' => $keeper,
        'user' => $user,
        'devices' => $list,
        'pager' => $pager,
    ]
);