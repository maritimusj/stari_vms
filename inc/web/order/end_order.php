<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$order_id = Request::int('id');

$order = Order::get($order_id);
if (empty($order)) {
    JSON::fail('对不起，找不到这个订单！');
}

if (!$order->isChargingBMSReportTimeout()) {
    JSON::fail('对不起，请确认订单状态异常后再试！');
}

$res = Charging::endOrder($order->getOrderNO(), '充电枪上报数据超时！');

if (is_error($res)) {
    JSON::fail($res);
}

JSON::success(['msg' => '已经结算订单，请刷新订单列表！']);