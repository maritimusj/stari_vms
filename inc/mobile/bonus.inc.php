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

    $res = Balance::dailySignIn($user);
    if (is_error($res)) {
        JSON::fail($res);
    }

    JSON::success([
        'balance' => $user->getBalance()->total(),
        'bonus' => $res,
    ]);

} elseif ($op == 'account') {

    $type = request::int('type', Account::NORMAL);
    $max = request::int('max', 10);

    $params = [
        'include' => [Account::BALANCE],
        'max' => $max,
    ];

    if ($type == Account::NORMAL) {
        $params['type'] = [Account::BALANCE];
    } else {
        $params['type'] = [$type];
        $params['s_type'] = []; //请求视频或者其它类型的公众号时，忽略第三方平台任务
    }

    $result = Account::getAvailableList(Device::getDummyDevice(), $user, $params);

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

} elseif ($op == 'logs') {

    $query = $user->getBalance()->log();
    if (request::has('lastId')) {
        $query->where(['id <' => request::int('lastId')]);
    }

    $query->limit(request::int('pagesize', 20));
    $query->orderBy('createtime DESC');

    $result = [];
    foreach ($query->findAll() as $entry) {
        $result[] = Balance::format($entry);
    }

    JSON::success($result);
}