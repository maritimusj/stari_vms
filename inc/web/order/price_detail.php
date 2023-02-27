<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');

$order = Order::get($id);
if (!$order) {
    JSON::fail('找不到这个订单！');
}

$content = app()->fetchTemplate(
    'web/charging/detail',
    [
        'data' => $order->getChargingRecord(),
    ]
);

JSON::success(['title' => '计费详情', 'content' => $content]);