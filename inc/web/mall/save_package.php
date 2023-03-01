<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$delivery = Delivery::get($id);
if (!$delivery) {
    JSON::fail('找不到这个商城订单！');
}

$uid = Request::trim('uid');
$carrier = Request::trim('carrier');
$memo = Request::trim('memo');

$package = [
    'uid' => $uid,
    'carrier' => $carrier,
    'memo' => $memo,
];

$delivery->setExtraData('package', $package);

$delivery->setStatus(Delivery::SHIPPING);

if ($delivery->save()) {
    JSON::success([
        'msg' => isEmptyArray($package) ? Delivery::formatStatus(Delivery::SHIPPING) : '已保存！',
        'title' => Delivery::formatStatus(Delivery::SHIPPING),
        'status' => Delivery::SHIPPING,
        'package' => $package,
    ]);
}

JSON::fail('保存失败！');