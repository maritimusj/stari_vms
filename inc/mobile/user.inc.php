<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

$op = Request::op('default');
$app_key = Request::str('appkey');

function findApiUser($app_key): ?userModelObj
{
    if (Request::has('uid')) {
        $uid = Request::str('uid');
        $user = User::findOne("SHA1(CONCAT('$app_key', id))='$uid'");
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

    $mobile = Request::trim('mobile');
    if ($mobile) {
        $query->where(['mobile' => $mobile]);
    }

    if (Request::isset('app')) {
        $query->where(['app' => Request::int('app')]);
    }

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    $result = [];

    /** @var userModelObj $user */
    foreach ($query->findAll() as $user) {
        $data = $user->profile(false, $app_key);
        $app = User::getUserCharacter($user);
        if ($app) {
            $data['app'] = [
                'id' => $app['id'],
                'name' => $app['name'],
                'title' => $app['title'],
            ];
        }
        $data['balance'] = $user->getBalance()->total();
        $result[] = $data;
    }

    JSON::result($result);

} elseif ($op == 'detail') {

    $user = findApiUser($app_key);

    if (empty($user)) {
        JSON::fail('用户不存在！');
    }

    $data = $user->profile(false, $app_key);

    $data['balance'] = $user->getBalance()->total();

    JSON::result($data);

} elseif ($op == 'update') {

    $user = findApiUser($app_key);

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

    if (!$result) {
        JSON::fail('操作失败！');
    }

    JSON::success([
        'balance' => $user->getBalance()->total(),
        'val' => $result->getXVal(),
    ]);
}