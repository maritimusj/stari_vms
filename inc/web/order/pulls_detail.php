<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$order = Order::get($id);
if (empty($order)) {
    JSON::fail('找不到这个订单！');
}

$list = Helper::getOrderPullLog($order);

$content = app()->fetchTemplate(
    'web/order/pulls',
    [
        'list' => $list,
    ]
);

JSON::success(['title' => '出货记录', 'content' => $content]);