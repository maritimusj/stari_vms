<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    if (Request::is_ajax()) {
        JSON::fail('找不到这个用户！');
    }
    Response::alert('用户不可用！', 'error');
}

$op = Request::op('default');
if ($op == 'default') {

    app()->mallPage([
        'user' => $user
    ]);

} elseif ($op == 'order') {

    app()->mallOrderPage([
        'user' => $user
    ]);

} elseif ($op == 'goods_list') {

    $result = Mall::getGoodsList([
        'page' => Request::int('page'),
        'pagesize' => Request::int('pagesize'),
    ]);

    JSON::success($result);

} elseif ($op == 'create_order') {

    $result = Mall::createOrder($user, [
        'goods_id' => Request::int('goods'),
        'num' => Request::int('num'),
    ]);

    JSON::result($result);

} elseif ($op == 'logs') {

    $params = [
        'last_id' => Request::int('lastId'),
        'pagesize' => Request::int('pagesize'),
        'user_id' => $user->getId(),
    ];

    if (Request::isset('status')) {
        $params['status'] = Request::int('status');
    }

    $result = Delivery::getList($params);

    JSON::success($result);

} elseif ($op == 'recipient') {

    $recipient = $user->getRecipientData();
    if (empty($recipient)) {
        $recipient = null;
    }

    JSON::success($recipient);

} elseif ($op == 'update_recipient') {

    $name = Request::trim('name');
    $phone_num = Request::trim('phoneNum');
    $address = Request::trim('address');

    $result = $user->updateRecipientData($name, $phone_num, $address);

    if ($result) {
        JSON::success('已保存！');
    }

    JSON::fail('保存失败！');
}