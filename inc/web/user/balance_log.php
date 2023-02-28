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

$title = "<b>{$user->getName()}</b>的积分记录";
$page = max(1, request::int('page'));
$page_size = request::int('pagesize', 5);

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

$content = app()->fetchTemplate(
    'web/common/balance_log',
    [
        'user' => $user,
        'logs' => $logs,
        'pager' => $pager,
    ]
);

JSON::success(['title' => $title, 'content' => $content]);