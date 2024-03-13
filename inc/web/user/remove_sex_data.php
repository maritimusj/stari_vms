<?php
namespace zovye;

use zovye\domain\User;

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$user->updateSettings('fansData.sex', null);

JSON::success('用户性别信息已清除!');