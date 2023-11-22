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

$log = Pay::getPayLog($order->getOrderNO());

if (empty($log)) {
    JSON::fail('找不到支付记录！');
}

$data = $log->getData();

$data['merchant_no'] = $data['payResult']['raw']['merchant_no'] ??
    $data['payResult']['merchant_no'] ??
    $data['queryResult']['merchant_no'] ??
    $data['payResult']['raw']['mch_id'] ??
    $data['payResult']['raw']['sub_mchid'] ??
    $data['payResult']['raw']['sn'] ??
    $data['queryResult']['sn'];

if ($data['refund']['result']) {
    $data['refund']['result']['total'] = $data['refund']['result']['data']['total_amount'] ??
        $data['refund']['result']['refund_fee'] ??
        $data['refund']['result']['amount']['refund'];
}

Response::templateJSON(
    'web/order/payment',
    '支付详情',
    [
        'data' => $data,
    ]
);