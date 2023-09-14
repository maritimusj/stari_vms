<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Account;
use zovye\domain\Balance;
use zovye\domain\Device;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$user = Session::getCurrentUser([
    'create' => true,
    'update' => true,
]);

if (empty($user)) {
    if (Request::is_ajax()) {
        JSON::fail('找不到这个用户！');
    }
    Response::alert('找不到这个用户！', 'error');
}

if ($user->isBanned()) {
    if (Request::is_ajax()) {
        JSON::fail('用户暂时不可用！');
    }
    Response::alert('用户暂时不可用！');
}

$op = Request::op('default');
if ($op == 'default') {

    $device_shadow_id = Request::str('device');
    if ($device_shadow_id) {
        $device = Device::findOne(['shadow_id' => $device_shadow_id]);
    }

    Response::bonusPage([
        'user' => $user,
        'device' => $device ?? null,
    ]);

} elseif ($op == 'home') {

    Response::userPage([
        'user' => $user,
    ]);

} elseif ($op == 'logsPage') {

    Response::userBalanceLogPage([
        'user' => $user,
    ]);

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

    $type = Request::int('type', Account::NORMAL);
    $max = Request::int('max', 10);

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

    $device_uid = Request::str('device');
    $goods_id = Request::int('goods');
    $num = Request::int('num');

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
    if (Request::has('lastId')) {
        $query->where(['id <' => Request::int('lastId')]);
    }

    $query->limit(Request::int('pagesize', 20));
    $query->orderBy('createtime DESC');

    $result = [];
    foreach ($query->findAll() as $entry) {
        $result[] = Balance::format($entry);
    }

    JSON::success($result);
}