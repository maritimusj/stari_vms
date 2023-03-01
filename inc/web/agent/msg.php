<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\msgModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$query = m('msg')->where(We7::uniacid([]));

$total = $query->count();

$pager = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id desc');

$messages = [];

/** @var msgModelObj $entry */
foreach ($query->findAll() as $entry) {
    $messages[] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
    ];
}

app()->showTemplate('web/agent/msg', [
    'op' => 'msg',
    'pager' => $pager,
    'messages' => $messages,
]);