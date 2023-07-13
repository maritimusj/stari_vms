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

$log = Pay::getPayLog($order->getOrderNO());

if (empty($log)) {
    JSON::fail('找不到支付记录！');
}

$data = $log->getData();

$data['merchant_no'] = $data['payResult']['raw']['merchant_no'] ??
    $data['payResult']['merchant_no'] ??
    $data['queryResult']['merchant_no'] ??
    $data['payResult']['raw']['mch_id'] ??
    $data['payResult']['raw']['sn'] ??
    $data['queryResult']['sn'];

$content = app()->fetchTemplate(
    'web/order/payment',
    [
        'data' => $data,
    ]
);

JSON::success(['title' => '支付详情', 'content' => $content]);