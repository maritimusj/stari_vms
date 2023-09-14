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

    if ($user) {
        $user->setState($user->getState() == 0 ? 1 : 0);

        if ($user->save()) {
            JSON::success(['msg' => '操作成功！', 'banned' => $user->isBanned()]);
        }
    }
}

JSON::fail('操作失败');