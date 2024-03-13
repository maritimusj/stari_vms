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
use zovye\util\Helper;
use zovye\util\Util;

$user = User::get(Request::int('user'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$keeper = $user->getKeeper();
if (empty($keeper)) {
    JSON::fail('这个用户不是运营人员！');
}

$query = Device::query(['keeper_id' => $keeper->getId()]);

$result = [];
/** @var keeper_devicesModelObj $item */
foreach ($query->findAll() as $index => $item) {
    $data = [
        $index + 1,
        $item->getName(),
        $item->getImei(),
    ];

    $data[] = $item->getKind() ? '允许' : '不允许';
    $data[] = $item->getWay() == 0 ? '销售分成' : '补货分成';
    
    if (App::isKeeperCommissionOrderDistinguishEnabled() && $item->getWay() == Keeper::COMMISSION_ORDER) {
        if ($item->isFixedValue()) {
            $data[] = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
            $data[] = number_format(abs($item->getCommissionFreeFixed()) / 100, 2).'元';
        } else {
            $data[] = number_format($item->getCommissionPercent() / 100, 2).'%';
            $data[] = number_format($item->getCommissionFreePercent() / 100, 2).'%';
        }
    } else {
        if ($item->isFixedValue()) {
            $data[] = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
            $data[] = number_format(abs($item->getCommissionFixed()) / 100, 2).'元';
        } else {
            $data[] = number_format($item->getCommissionPercent() / 100, 2).'%';
            $data[] = number_format($item->getCommissionPercent() / 100, 2).'%';
        }
    }

    $result[] = $data;
}

$headers = [
    '#',
    '设备名称',
    '设备编号',
    '补货权限',
    '分成方式',
    '收费订单分成金额/比例',
    '免费订单分成金额/比例',
];

$filename = date("YmdHis").'.csv';
$dirname = "export/keeper_device/";

$full_filename = Helper::getAttachmentFileName($dirname, $filename);

Util::exportCSVToFile($full_filename, $headers, $result);

JSON::success([
    'filename' => Util::toMedia("$dirname$filename"),
]);