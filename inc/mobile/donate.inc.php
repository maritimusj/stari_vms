<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isDonatePayEnabled()) {
    JSON::fail('没有启用这个功能！');
}
$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户被禁用！');
}


$op = request::op('default');
if ($op == 'default') {
    //检查设备
    $device_id = request::str('device');
    if (empty($device_id)) {
        JSON::fail('请扫描设备二维码，谢谢！');
    }

    $device = Device::find($device_id, ['imei', 'shadow_id']);
    if (empty($device)) {
        JSON::fail('设备二维码不正确！');
    }

    $donatePay = Config::donatePay('qsc');
    if (empty($donatePay['url'])) {
        JSON::fail('没有正确配置这个功能！');
    }

    //获取第一货道上的商品，如果该商品数量不足，则去获取其它货道上的相同商品
    $goods = $device->getGoodsByLane(0);
    if ($goods && $goods['num'] < 1) {
        $goods = $device->getGoods($goods['id']);
    }

    if (empty($goods) || $goods['num'] < 1) {
        JSON::fail('商品库存不足！');
    }

    $order_no = DonatePay::createPayLog($device, $user, $goods);
    if (is_error($order_no)) {
        JSON::fail($order_no);
    }

    $url = Util::murl('donate', ['op' => 'result', 'no' => $order_no]);
    $donatePay['url'] = str_replace('{url}', urlencode($url), $donatePay['url']);

    JSON::success($donatePay);

} elseif ($op == 'result') {
    $order_no = request::trim('no');
    if (empty($order_no)) {
        Util::resultAlert('不正确的调用[101]！', 'error');
    }

    if (!Locker::try("donate:$order_no")) {
        Util::resultAlert('不正确的调用[102]！', 'error');
    }

    if (Order::exists($order_no)) {
        Util::resultAlert('已完成爱心捐款，谢谢！', 'success');
    }

    $paylog = Pay::getPayLog($order_no);
    if (empty($paylog)) {
        Util::resultAlert('找不到支付记录，请联系管理员，谢谢！', 'error');
    }

    if ($paylog->getUserOpenid() !== $user->getOpenid()) {
        Util::resultAlert('不正确的调用[103]！', 'error');
    }

    $device = Device::get($paylog->getDeviceId());
    if (empty($device)) {
        Util::resultAlert('找不到指定的设备！', 'error');
    }

    $payResult =  [
        'result' => 'success',
        'type' => 'donate',
        'orderNO' => $order_no,
        'transaction_id' => REQUEST_ID,
        'total' => $paylog->getTotal(),
        'paytime' => time(),
        'openid' => $user->getOpenid(),
        'deviceUID' => $device->getImei(),
    ];

    $paylog->setData('payResult', $payResult);

    $paylog->setData('create_order.createtime', time());
    if (!$paylog->save()) {
        Util::resultAlert('无法保存数据！', 'error');
    }

    $res = Job::createOrder($order_no);
    if (!$res) {
        Util::resultAlert('无法启动出货任务！', 'error');
    }

    Util::redirect(Util::murl('payresult', ['orderNO' => $order_no]));
}