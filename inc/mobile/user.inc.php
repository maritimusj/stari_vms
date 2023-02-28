<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

$op = Request::op('default');
$app_key = Request::str('appkey');

function findApiUser() :? userModelObj {
    if (Request::has('id')) {
        $user = User::get(Request::int('id'));
    } elseif (Request::has('openid')) {
        $user = User::get(Request::str('openid'), true);
    } elseif (Request::has('mobile')) {
        $user = User::findOne(['mobile' => Request::str('mobile'), 'app' => Request::int('app', User::WX)]);
    }
    return $user ?? null;
}

if (empty($app_key) || $app_key !== Config::balance('app.key')) {
    JSON::fail('非法请求！');
}

if ($op == 'default') {

    JSON::success('Ok');

} elseif ($op == 'list') {

    $query = User::query();

    $page = Request::int('page', 1);
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

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

    $user = findApiUser();

    if (empty($user)) {
        JSON::fail('用户不存在！');
    }

    $data = $user->profile();
    $data['balance'] = $user->getBalance()->total();
    JSON::result($data);

} elseif ($op == 'update') {

    $user = findApiUser();

    if (empty($user)) {
        JSON::fail('用户不存在！');
    }

    $val = Request::int('val');
    if (empty($val)) {
        JSON::fail('积分值不能为0！');
    }

    $result = $user->getBalance()->change($val, Balance::API_UPDATE, [
        'appkey' => $app_key,
        'reason' => Request::str('reason', '', true),
        'ip' => Util::getClientIp(),
    ]);

    JSON::success([
        'balance' => $user->getBalance()->total(),
        'val' => $result->getXVal(),
    ]);
}