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

$data = [
    'way' => 'num',
    'id' => $order->getId(),
    'num' => $order->getNum(),
    'price' => number_format($order->getPrice() / 100, 2),
    'orderId' => $order->getOrderId(),
    'createtime' => date('Y-m-d H:i:s', $order->getCreatetime()),
];

if ($order->isPackage() || $order->isFuelingOrder()) {
    $data['way'] = 'money';
}

$pay_result = $order->getExtraData('payResult');
$data['transaction_id'] = $pay_result['transaction_id'] ?? ($pay_result['uniontid'] ?? $data['orderId']);

$tpl = [
    'order' => $data,
];

$user = $order->getUser();
if (!empty($user)) {
    $tpl['user'] = $user->profile();
}

$content = app()->fetchTemplate('web/order/refund', $tpl);

JSON::success([
    'title' => '订单退款',
    'content' => $content,
]);