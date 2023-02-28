<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\articleModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = m('article')->where(We7::uniacid(['type' => 'article']));

$total = $query->count();

$tpl_data = [
    'archive_types' => settings('doc.types'),
];

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id desc');

$articles = [];

/** @var articleModelObj $entry */
foreach ($query->findAll() as $entry) {
    $articles[] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'total' => intval($entry->getTotal()),
        'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
    ];
}

$tpl_data['articles'] = $articles;

app()->showTemplate('web/doc/article', $tpl_data);