<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

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

} elseif ($op == 'order') {

    app()->mallOrderPage($user);

} elseif ($op == 'goods_list') {

    $result = Mall::getGoodsList([
        'page' => request::int('page'),
        'pagesize' => request::int('pagesize'),
    ]);

    JSON::success($result);

} elseif ($op == 'create_order') {
    $result = Mall::createOrder($user, [
        'goods_id' => request::int('goods'),
        'num' => request::int('num'),
    ]);

    JSON::result($result);

} elseif ($op == 'logs') {

    $params = [
        'last_id' => request::int('lastId'),
        'pagesize' => request::int('pagesize'),
        'user_id' => $user->getId(),
    ];

    if (request::isset('status')) {
        $params['status'] = request::int('status');
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

    $name = request::trim('name');
    $phone_num = request::trim('phoneNum');
    $address = request::trim('address');

    $result = $user->updateRecipientData($name, $phone_num, $address);

    if ($result) {
        JSON::success('已保存！');
    }

    JSON::fail('保存失败！');
}