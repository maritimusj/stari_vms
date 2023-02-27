<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\articleModelObj;

$page = max(1, request::int('page'));
$page_size = request::int('pagesize', 10);

$query = m('article')->where(We7::uniacid(['type' => 'faq']));

$total = $query->count();

$tpl_data = [
    'archive_types' => settings('doc.types'),
];

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id desc');

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

app()->showTemplate('web/doc/faq', $tpl_data);