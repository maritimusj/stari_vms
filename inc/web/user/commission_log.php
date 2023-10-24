<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\CommissionBalance;
use zovye\domain\Principal;
use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}
$name = $user->getName() ?: '<匿名用户>';
$title = "<b>{$name}</b>的余额变动记录";
$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 5);

$query = $user->getCommissionBalance()->log();

$total = $query->count();

$pager = '';

$logs = [];
if ($total > 0) {
    //检查有佣金记录的用户的佣金用户身份是否存在
    if (!$user->isGSPor()) {
        $user->setPrincipal(Principal::Gspor);
        $user->save();
    }

    $pager = We7::pagination($total, $page, $page_size);
    $query->page($page, $page_size);
    $query->orderBy('createtime DESC');

    foreach ($query->findAll() as $entry) {
        $logs[] = CommissionBalance::format($entry);
    }
}

Response::templateJSON(
    'web/common/commission_log',
    $title,
    [
        'user' => $user,
        'logs' => $logs,
        'pager' => $pager,
    ]
);