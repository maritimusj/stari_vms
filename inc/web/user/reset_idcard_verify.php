<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $user = User::get($id);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    if ($user->setIDCardVerified() && $user->save()) {
        JSON::success('已清除用户的实名认证信息！');
    }
}
