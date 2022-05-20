<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

$op = request::op('default');
$app_key = request::str('appkey');

if (empty($app_key) || $app_key !== Config::balance('app.key')) {
    JSON::fail('非法请求！');
}

if ($op == 'default') {
    JSON::success('Ok');
} elseif ($op == 'list') {
    $query = User::query();

    $page = request::int('page', 1);
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $result = [];

    /** @var userModelObj $user */
    foreach ($query->findAll() as $user) {
        $data = $user->profile(true);
        $data['balance'] = $user->getBalance()->total();
        $result[] = $data;
    }

    JSON::result($result);

} elseif ($op == 'detail') {

    if (request::has('id')) {
        $user = User::get(request::int('id'));
    } elseif (request::has('openid')) {
        $user = User::get(request::str('openid'), true);
    }

    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }
    $data = $user->profile(true);
    $data['balance'] = $user->getBalance()->total();
    JSON::result($data);

} elseif ($op == 'update') {

    if (request::has('id')) {
        $user = User::get(request::int('id'));
    } elseif (request::has('openid')) {
        $user = User::get(request::str('openid'), true);
    }

    $val = request::int('val');
    if (empty($val)) {
        JSON::fail('积分值不能为0！');
    }

    if (empty($user)) {
        JSON::fail('用户不存在！');
    }

    $result = $user->getBalance()->change($val, Balance::API_UPDATE, [
        'appkey' => $app_key,
        'reason' => request::str('reason', '', true),
        'ip' => Util::getClientIp(),
    ]);

    JSON::success([
        'balance' => $user->getBalance()->total(),
        'val' => $result->getXVal(),
    ]);
}