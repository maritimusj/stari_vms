<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$user = User::get(request::int('id'));
if (empty($user)) {
    JSON::fail('没有找到这个用户！');
}

$total = intval(round(request::float('total', 0, 2) * 100));
if ($total == 0) {
    JSON::fail('金额不能为零！');
}

if ($user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
    $memo = request::str('memo');
    $r = $user->commission_change(
        $total,
        CommissionBalance::ADJUST,
        [
            'admin' => _W('username'),
            'ip' => CLIENT_IP,
            'user-agent' => $_SERVER['HTTP_USER_AGENT'],
            'memo' => $memo,
        ]
    );
    if ($r && $r->update([], true)) {
        JSON::success('操作成功 ！');
    }
}

JSON::fail('保存数据失败！');