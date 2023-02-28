<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');

$order = Order::get($id);
if (empty($order)) {
    JSON::fail('找不到这个订单！');
}

if ($order->isFuelingFinished()) {
    JSON::fail('这个订单已经结束！');
}

$device = $order->getDevice();
if (!$device) {
    JSON::fail('找不到这个设备！');
}

Fueling::checkUnfinishedOrder($device, "force");

JSON::success('已强制停止关联订单，请刷新页面检查订单情况！');