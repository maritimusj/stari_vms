<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$user = Util::getCurrentUser([
    'create' => true,
    'update' => true,
]);

if (empty($user)) {
    if (request::is_ajax()) {
        JSON::fail('找不到这个用户！');
    }
    Util::resultAlert('找不到这个用户！', 'error');
}

if ($user->isBanned()) {
    if (request::is_ajax()) {
        JSON::fail('用户暂时不可用！');
    }
    Util::resultAlert('用户暂时不可用！');
}

$op = request::op('default');
if ($op == 'default') {

    app()->bonusPage($user);

} elseif ($op == 'home') {

    app()->userPage($user);

} elseif ($op == 'logsPage') {

    app()->userBalanceLogPage($user);

} elseif ($op == 'signIn') {

    $bonus = Config::balance('sign.bonus', []);
    if (empty($bonus) || !$bonus['enabled'] || empty($bonus['val'])) {
        JSON::fail('这个功能没有启用！');
    }

    if (!$user->acquireLocker("balance:daily:sign_in")) {
        JSON::fail('请稍后再试！');
    }

    if ($user->isSigned()) {
        JSON::fail('已经签到了！');
    }

    $res = $user->signIn($bonus['val']);
    if (empty($res)) {
        JSON::fail('签到失败！');
    }

    JSON::success([
        'balance' => $user->getBalance()->total(),
        'bonus' => $bonus['val'],
    ]);

} elseif ($op == 'account') {

    $type = request::int('type', Account::NORMAL);
    $max = request::int('max', 10);

    $result = Account::getAvailableList(Device::getBalanceVDevice(), $user, [
        'type' => [$type],
        'include' => [Account::BALANCE],
        'max' => $max,
    ]);

    JSON::success($result);

} elseif ($op == 'exchange') {

    if (!App::isBalanceEnabled()) {
        JSON::fail('这个功能没有启用！');
    }

    $device_uid = request::str('device');
    $device = Device::get($device_uid, true);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $goods_id = request::int('goods');

    $goods = $device->getGoods($goods_id);
    if (empty($goods) || empty($goods['balance'])) {
        JSON::fail('无法兑换这个商品，请联系管理员！');
    }

    $num = min(App::orderMaxGoodsNum(), max(request::int('num'), 1));
    if (empty($num) || $num < 1) {
        JSON::fail('对不起，商品数量不正确！');
    }

    if ($goods['num'] < $num) {
        JSON::fail('对不起，商品数量不足！');
    }

    if (!$user->acquireLocker(User::BALANCE_LOCKER)) {
        JSON::fail('无法锁定用户，请稍后再试！');
    }

    $balance = $user->getBalance();
    $total = $goods['balance'] * $num;

    if ($balance->total() < $total) {
        JSON::fail('您的积分不够！');
    }

    $result = $user->getBalance()->change(-$total, Balance::GOODS_EXCHANGE, [
        'user' => $user->profile(),
        'device' => $device->profile(),
        'goods' => $goods,
        'num' => $num,
        'ip' => $user->getLastActiveData('ip') ?: Util::getClientIp(),
    ]);

    if (empty($result)) {
        JSON::fail('积分操作失败，请联系管理员！');
    }

    $order_no = Order::makeUID($user, $device, sha1($result->getId() . $result->getCreatetime()));
    if (Job::createBalanceOrder($order_no, $result)) {
        JSON::success([
            'msg' => '请稍后，正在出货中！',
            'redirect' => Util::murl('payresult', ['orderNO' => $order_no, 'balance' => $result->getId()]),
        ]);
    }

    JSON::success('失败，请稍后再试！');

}  elseif ($op == 'logs') {

    $query = $user->getBalance()->log();
    if (request::has('lastId')) {
        $query->where(['id <' => request::int('lastId')]);
    }

    $query->limit(request::int('pagesize', 20));
    $query->orderBy('createtime DESC');

    $result = [];
    foreach($query->findAll() as $entry) {
        $result[] = Balance::format($entry);
    }

    JSON::success($result);
}