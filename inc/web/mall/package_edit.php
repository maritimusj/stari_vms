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

$package = $delivery->getExtraData('package', []);

Response::templateJSON(
    'web/mall/package_edit',
    "发货信息[ {$delivery->getOrderNo()} ]",
    [
        'id' => $delivery->getId(),
        'package' => $package,
    ]
);
