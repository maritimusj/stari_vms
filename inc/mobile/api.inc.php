<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

$app_key = request::str('appkey');
if ($app_key !== settings('app.key')) {
    JSON::fail('appkey不正确！');
}

$account_name = request::str('account');
if (empty($account_name)) {
    $account_name = settings('api.account');
}
if (empty($account_name)) {
    JSON::fail('没有指定公众号！');
}

$account = Account::findOne(['name' => $account_name]);
if (empty($account)) {
    JSON::fail('找不到指定的公众号！');
}

if ($account->isBanned()) {
    JSON::fail('公众号被禁用！');
}

$user_uid = request::str('user');
if (empty($user_uid)) {
    JSON::fail('没有指定用户uid！');
}

$user = User::get($user_uid, true, USER::API);
if (empty($user)) {
    $user = User::create([
        'app' => User::API,
        'nickname' => Util::random(6),
        'avatar' => User::API_USER_HEAD_IMG,
        'openid' => $user_uid,
    ]);
}

if (empty($user)) {
    JSON::fail('创建用户失败！');
}

if ($user->isBanned()) {
    JSON::fail('用户已禁用！');
}

$device_imei = request::str('device');
$device = Device::get($device_imei, true);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if ($device->isDown()) {
    JSON::fail('设备维护中，请稍后再试！');
}

if (!$device->isMcbOnline()) {
   JSON::fail('设备不在线！');
}

$res = Util::isAvailable($user, $account, $device);
if (is_error($res)) {
    JSON::fail($res['message']);
}

//获取第一货道上的商品，如果该商品数量不足，则去获取其它货道上的相同商品
$goods = $device->getGoodsByLane(0);
if ($goods && $goods['num'] < 1) {
    $goods = $device->getGoods($goods['id']);
}

if (empty($goods) || $goods['num'] < 1) {
    JSON::fail('商品库存不足！');
}

$order_uid = Order::makeUID($user, $device);

Job::createSpecialAccountOrder([
    'device' => $device->getId(),
    'user' => $user->getId(),
    'account' => $account->getId(),
    'orderUID' => $order_uid,
]);

JSON::success('成功！');