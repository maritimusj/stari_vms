<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Order;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$order = Order::get($id);
if (empty($order)) {
    JSON::fail('找不到这个订单！');
}

$list = Helper::getOrderPullLog($order);

Response::templateJSON(
    'web/order/pulls',
    "出货记录 [ {$order->getOrderId()} ]",
    [
        'list' => $list,
    ]
);