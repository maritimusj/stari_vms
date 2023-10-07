<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Principal;
use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

if (!App::isPromoterEnabled()) {
    Response::data(err('这个功能没有启用！'), true);
}

$user_id = Request::int('id');

$user = User::get($user_id);
if (empty($user)) {
    Response::data(err('找不到这个用户！'), true);
}

if ($user->removePrincipal(Principal::Promoter)) {
    Response::data('删除成功！', true);
}

Response::data('删除失败！', true);
