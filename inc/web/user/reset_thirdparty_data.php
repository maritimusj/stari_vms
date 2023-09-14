<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $user = User::get($id);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->remove('customData') && $user->save()) {
        JSON::success('已清除用户的第三方平台信息！');
    }
}
