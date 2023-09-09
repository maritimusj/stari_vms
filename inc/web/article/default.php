<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\articleModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Article::query(['type' => 'article']);

$total = $query->count();

$tpl_data = [
    'archive_types' => settings('doc.types'),
];

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id DESC');

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

Response::showTemplate('web/doc/article', $tpl_data);