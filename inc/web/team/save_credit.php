<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

$user_id = Request::int('id');
$user = User::get($user_id);

if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$val = Request::float('val', 0, 2) * 100;

if ($user->setCredit($val)) {
    JSON::success('已保存！');
}

JSON::fail('保存失败！');