<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isPromoterEnabled()) {
    Util::resultData(err('这个功能没有启用！'), true);
}

$user_id = Request::int('id');

$user = User::get($user_id);
if (empty($user)) {
    Util::resultData(err('找不到这个用户！'), true);
}

if ($user->removePrincipal(Principal::Promoter)) {
    Util::resultData('删除成功！', true);
}

Util::resultData('删除失败！', true);
