<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Delivery;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$delivery = Delivery::get($id);
if (!$delivery) {
    JSON::fail('找不到这个商城订单！');
}

$status = Request::int('status');
$delivery->setStatus($status);
if ($delivery->save()) {
    JSON::success([
        'msg' => Delivery::formatStatus($status),
        'status' => $status,
    ]);
}

JSON::fail('操作失败！');