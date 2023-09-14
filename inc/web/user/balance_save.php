<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Balance;
use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('没有找到这个用户！');
}

$total = Request::int('total');
if ($total == 0) {
    JSON::fail('积分数量不能为零！');
}

if ($user->acquireLocker(User::BALANCE_LOCKER)) {
    $memo = Request::str('memo');
    $r = $user->getBalance()->change(
        $total,
        Balance::ADJUST,
        [
            'admin' => _W('username'),
            'ip' => CLIENT_IP,
            'user-agent' => $_SERVER['HTTP_USER_AGENT'],
            'memo' => $memo,
        ]
    );
    if ($r) {
        JSON::success('操作成功 ！');
    }
}

JSON::fail('保存数据失败！');