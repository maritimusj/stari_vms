<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Order;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$log = Pay::getPayLogById($id);
if (empty($log)) {
    JSON::fail('找不到这个支付记录！');
}

$user = $log->getOwner();
if (empty($user) || !$user->isAccessible()) {
    JSON::fail('没有权限管理！');
}

$total = 0;

$result = Pay::refundByLog($log, $total, ['message' => '管理员退款[003]']);
if (is_error($result)) {
    JSON::fail($result);
}

$order = Order::get($log->getOrderNO(), true);
if ($order) {
    $order->setExtraData(
        'refund',
        array_merge($result, [
            'total' => $total,
        ])
    );

    $order->setRefund(Order::REFUND);
    $order->save();
}

JSON::success('退款成功， 退款金额：'.number_format($total / 100, 2, '.', '').'元');