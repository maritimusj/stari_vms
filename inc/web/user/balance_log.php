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
    JSON::fail('找不到这个用户！');
}

$title = "<b>{$user->getName()}</b>的积分记录";
$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 5);

$query = $user->getBalance()->log();

$total = $query->count();

$pager = '';

$logs = [];
if ($total > 0) {
    $pager = We7::pagination($total, $page, $page_size);
    $query->page($page, $page_size);
    $query->orderBy('createtime DESC');

    foreach ($query->findAll() as $entry) {
        $logs[] = Balance::format($entry);
    }
}

Response::templateJSON(
    'web/common/balance_log',
    $title,
    [
        'user' => $user,
        'logs' => $logs,
        'pager' => $pager,
    ]
);