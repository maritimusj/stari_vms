<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isPromoterEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$user_id = Request::int('id');

$user = User::get($user_id);
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

if ($user->removePrincipal(Principal::Promoter)) {
    JSON::success([
        'id' => $user->getId(),
    ]);
}

JSON::fail('删除失败！');
