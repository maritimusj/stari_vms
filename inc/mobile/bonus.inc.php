<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;

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

    $device_shadow_id = request::str('device');
    if ($device_shadow_id) {
        $device = Device::findOne(['shadow_id' => $device_shadow_id]);
    }
    
    app()->bonusPage($user, $device ?? null);

} elseif ($op == 'home') {

    app()->userPage($user);

} elseif ($op == 'logsPage') {

    app()->userBalanceLogPage($user);

} elseif ($op == 'signIn') {

    $bonus = Config::balance('sign.bonus', []);
    if (empty($bonus) || !$bonus['enabled']) {
        JSON::fail('这个功能没有启用！');
    }

    if (!$user->acquireLocker("balance:daily:sign_in")) {
        JSON::fail('请稍后再试！');
    }

    if ($user->isSigned()) {
        JSON::fail('已经签到了！');
    }

    $min = intval($bonus['min']);
    $max = intval($bonus['max']);

    if ($min >= $max) {
        $val = $min;
    } else {
        try {
            $val = random_int($min, $max);
        } catch (Exception $e) {
        }
    }
    
    if (empty($val)) {
        JSON::fail('真遗憾，没有获得积分！');
    }

    $res = $user->signIn($val);
    if (empty($res)) {
        JSON::fail('签到失败！');
    }

    JSON::success([
        'balance' => $user->getBalance()->total(),
        'bonus' => $val,
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

    $device_uid = request::str('device');
    $goods_id = request::int('goods');
    $num = request::int('num');

    $res = Helper::exchange($user, $device_uid, $goods_id, $num);
    if (is_error($res)) {
        JSON::fail($res);
    }

    JSON::success([
        'msg' => '请稍后，正在出货中！',
        'redirect' => Util::murl('payresult', ['orderNO' => $res, 'balance' => 1]),
    ]);

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