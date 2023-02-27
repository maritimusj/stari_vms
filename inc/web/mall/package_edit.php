<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$delivery = Delivery::get($id);
if (!$delivery) {
    JSON::fail('找不到这个商城订单！');
}

$package = $delivery->getExtraData('package', []);

$content = app()->fetchTemplate('web/mall/package_edit', [
    'id' => $delivery->getId(),
    'package' => $package,
]);

JSON::success(['title' => "发货信息[ {$delivery->getOrderNo()} ]", 'content' => $content]);
