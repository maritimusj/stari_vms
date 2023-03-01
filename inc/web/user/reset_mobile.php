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

    if ($user->isAgent() || $user->isPartner() || $user->isKeeper()) {
        JSON::fail('无法操作，请先删除用户身份！');
    }

    if ($user->setMobile('') && $user->save()) {
        JSON::success('已清除用户的手机号码！');
    }
}