<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$user = User::get(request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$title = "<b>{$user->getName()}</b>的余额变动记录";
$page = max(1, request::int('page'));
$page_size = request::int('pagesize', 5);

$query = $user->getCommissionBalance()->log();

$total = $query->count();

$pager = '';

$logs = [];
if ($total > 0) {
    //检查有佣金记录的用户的佣金用户身份是否存在
    if (!$user->isGSPor()) {
        $user->setPrincipal(User::GSPOR);
        $user->save();
    }

    $pager = We7::pagination($total, $page, $page_size);
    $query->page($page, $page_size);
    $query->orderBy('createtime DESC');

    foreach ($query->findAll() as $entry) {
        $logs[] = CommissionBalance::format($entry);
    }
}

$content = app()->fetchTemplate(
    'web/common/commission_log',
    [
        'user' => $user,
        'logs' => $logs,
        'pager' => $pager,
    ]
);

JSON::success(['title' => $title, 'content' => $content]);