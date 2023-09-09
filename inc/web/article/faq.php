<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\articleModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$query = Article::query(['type' => 'faq']);

$total = $query->count();

$tpl_data = [
    'archive_types' => settings('doc.types'),
];

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id DESC');

$faq = [];

/** @var articleModelObj $entry */
foreach ($query->findAll() as $entry) {
    $faq[] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'content' => $entry->getContent(),
        'total' => intval($entry->getTotal()),
        'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
    ];
}

$tpl_data['faq'] = $faq;

Response::showTemplate('web/doc/faq', $tpl_data);