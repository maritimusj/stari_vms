<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$app_key = Request::str('appkey');
if (empty($app_key) || $app_key !== settings('app.key')) {
    JSON::fail('appkey不正确！');
}

/**
 * 查询订单状态
 */
if (Request::has('orderUID')) {
    $order_no = Request::str('orderUID');
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        JSON::fail('找不到这个订单的支付记录！');
    }

    $err = $pay_log->getData('create_order.error');
    if (is_error($err)) {
        JSON::fail($err);
    }

    $order = Order::get($order_no, true);
    if (empty($order)) {
        JSON::success(
            [
                'code' => 100,
                'msg' => '正在创建订单中！',
            ]
        );
    }

    $result = $order->getExtraData('pull', []);
    $device = $order->getDevice();
    if ($device) {
        $goods = $device->getGoods($order->getGoodsId());
        if ($goods) {
            $result['goods'] = [
                'id' => $goods['id'],
                'name' => $goods['name'],
                'image' => Util::toMedia($goods['img'], true),
                'price' => $goods['price_formatted'],
                'num' => $goods['num'],
                'unit' => $goods['unit_title'],
            ];            
        }
    }

    JSON::success($result);
}

$user_uid = Request::str('user');
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

$device_imei = Request::str('device');
$device = Device::find($device_imei, ['imei', 'shadow_id']);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if ($device->isDown()) {
    JSON::fail('设备维护中，请稍后再试！');
}

if (!$device->isMcbOnline()) {
    JSON::fail('设备不在线！');
}

$order_no = Order::makeUID($user, $device);

$data = [
    'device' => $device->getId(),
    'user' => $user->getId(),
    'orderUID' => $order_no,
];

$price = Request::int('price');
if (empty($price)) {
    /**
     * 公众号免费出货
     */
    $account_uid = settings('api.account');
    if (empty($account_uid)) {
        JSON::fail('没有指定公众号！');
    }

    $account = Account::findOneFromUID($account_uid);
    if (empty($account)) {
        JSON::fail('找不到指定的公众号！');
    }

    if ($account->isBanned()) {
        JSON::fail('公众号被禁用！');
    }

    $res = Helper::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);
    if (is_error($res)) {
        JSON::fail($res['message']);
    }

    $data['account'] = $account->getId();

} else {
    /**
     * 第三方API收费订单
     */
    $num = Request::int('num', 1);
    if ($num < 1 || $num > App::getOrderMaxGoodsNum()) {
        JSON::fail("商品数量超出限制！");
    }

    $data['num'] = $num;
    $data['price'] = $price;
}

//获取第一货道上的商品，如果该商品数量不足，则去获取其它货道上的相同商品
$goods = $device->getGoodsByLane(0);
if ($goods && $goods['num'] < $data['num']) {
    $goods = $device->getGoods($goods['id']);
}

if (empty($goods) || $goods['num'] < $data['num']) {
    JSON::fail('商品库存不足！');
}

/**
 * 检查用户是否符合出货要求
 */
if (Request::bool('verify')) {
    JSON::success('成功！');
}

if (empty($price)) {
    if (!Job::createThirdPartyPlatformOrder($data)) {
        JSON::fail("启动订单任务失败！");
    }
} else {
    /**
     * 创建支付记录
     */
    $total = $data['num'];
    $goods['num'] = $total;
    $pay_log = Pay::createPayLog($user, $order_no, [
        'device' => $device->getId(),
        'user' => $user->getOpenid(),
        'goods' => $goods['id'],
        'level' => LOG_GOODS_PAY,
        'total' => $total,
        'pay' => [
            'name' => 'api',
        ],
        'orderData' => [
            'orderNO' => $order_no,
            'num' => $total,
            'price' => $price,
            'ip' => CLIENT_IP,
            'extra' => [
                'goods' => $goods,
            ],
            'createtime' => time(),
        ],
        'payResult' => [
            'type' => 'api',
            'result' => 'success',
        ],
    ]);
    if (empty($pay_log)) {
        JSON::fail("创建支付记录失败！");
    }
    if (!Job::createOrder($order_no)) {
        JSON::fail("启动订单任务失败！");
    }
}

JSON::success([
    'orderUID' => $order_no,
    'msg' => '成功！',
]);