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

if (!$order->isRefund()) {
    JSON::fail('订单没有退款！');
}

$log = Pay::getPayLog($order->getOrderNO());

if (empty($log)) {
    JSON::fail('找不到支付记录！');
}

$refund = $log->getData('refund', []);

if (empty($refund)) {
    JSON::fail('没有退款数据！');
}


