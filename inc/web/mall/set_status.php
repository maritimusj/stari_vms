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

$status = request::int('status');
$delivery->setStatus($status);
if ($delivery->save()) {
    JSON::success([
        'msg' => Delivery::formatStatus($status),
        'status' => $status,
    ]);
}

JSON::fail('操作失败！');