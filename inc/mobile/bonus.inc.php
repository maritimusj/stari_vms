<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$op = request::op('default');
if ($op == 'default') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('找不到这个用户！', 'error');
    }
    if ($user->isBanned()) {
        Util::resultAlert('用户暂时不可用！');
    }

    app()->bonusPage($user);

}
if ($op == 'signIn') {

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->isBanned()) {
        JSON::fail('用户暂时不可用！');
    }

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

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->isBanned()) {
        JSON::fail('用户暂时不可用！');
    }

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

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('用户无法使用该功能！');
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

    if (Job::createBalanceOrder($result)) {
        JSON::success('请稍后，正在出货中！');
    }

    JSON::success('失败，请稍后再试！');
}   