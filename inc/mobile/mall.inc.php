<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\balanceModelObj;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    if (request::is_ajax()) {
        JSON::fail('找不到这个用户！');
    }
    Util::resultAlert('用户不可用！', 'error');
}

$op = request::op('default');
if ($op == 'default') {

    app()->mallPage($user);

} elseif ($op == 'goods_list') {

    $result = Goods::getList([
        'page' => request::int('page'),
        'pagesize' => request::int('pagesize'),
        Goods::AllowDelivery,
    ]);

    JSON::success($result);

} elseif ($op == 'create_order') {

    $goods_id = request::int('goods');
    $goods = Goods::get($goods_id);
    if (empty($goods)) {
        JSON::fail('找不到这个商品！');
    }
    if (!$goods->allowDelivery()) {
        JSON::fail('无法兑换这个商品！');
    }

    $num = request::int('num', 1);
    $num = min(App::orderMaxGoodsNum(), max($num, 1));
    if ($num < 1) {
        JSON::fail('对不起，商品数量不正确！');
    }

    if ($goods['num'] < $num) {
        JSON::fail('对不起，商品数量不足！');
    }

    $name = request::trim('name');
    $phone_num = request::trim('phone_num');
    $address = request::trim('address');

    if (empty($phone_num) || empty($address)) {
        JSON::fail('没有收件人的手机号码或地址！');
    }

    if (!$user->acquireLocker(User::ORDER_LOCKER)) {
        JSON::fail('无法锁定用户，请稍后再试！');
    }

    $balance = $user->getBalance();

    $result = Util::transactionDo(function () use ($user, $balance, $goods, $num, $phone_num, $address, $name) {
        if ($goods['balance'] * $num > $balance->total()) {
            return err('您的积分不够！');
        }
        $x = $balance->change(-$goods['balance'] * $num, Balance::DELIVERY_ORDER, [
            'goods' => $goods['goods_id'],
            'num' => $num,
        ]);
        if (empty($x)) {
            return err('积分操作失败！');
        }

        $order = Delivery::create([
            'user_id' => $user->getId(),
            'phone_num' => $phone_num,
            'address' => $address,
            'status' => Delivery::PAYED,
            'extra' => [
                'recipient' => [
                    'name' => $name,
                ],
                'goods' => $goods,
                'num' => $num,
                'balance' => [
                    'id' => $x->getId(),
                    'xval' => $x->getXVal(),
                ],
            ]
        ]);

        if (empty($order)) {
            return err('创建订单出错！');
        }

        $x->setExtraData('order.id', $order->getId());
        if (!$x->save()) {
            return err('保存数据失败！');
        }

        return $x;
    });


    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success([
        'total' => $balance->total(),
        'xval' => $result instanceof balanceModelObj ? $result->getXval() : 0,
    ]);

} elseif ($op == 'logs') {

    $params = [
        'page' => request::int('page'),
        'pagesize' => request::int('pagesize'),
        'user_id' => $user->getId(),
    ];

    if (request::isset('status')) {
        $params['status'] = request::int('status');
    }

    $result = Delivery::getList($params);

    JSON::success($result);

} elseif ($op == 'recipient') {

    JSON::success($user->getRecipientData());

} elseif ($op == 'update_recipient') {
    $name = request::trim('name');
    $phone_num = request::trim('phoneNum');
    $address = request::trim('address');

    $result = $user->updateRecipientData($name, $phone_num, $address);

    if ($result) {
        JSON::fail('保存失败！');
    }
    JSON::fail('已保存！');
}