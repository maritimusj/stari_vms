<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\filesModelObj;

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = m('files')->where(We7::uniacid([]));

$total = $query->count();

$tpl_data = [
    'archive_types' => settings('doc.types'),
];

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id DESC');

$archives = [];

/** @var filesModelObj $entry */
foreach ($query->findAll() as $entry) {
    $archives[] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'type' => $entry->getType(),
        'url' => $entry->getUrl(),
        'total' => intval($entry->getTotal()),
        'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
    ];
}

$tpl_data['archives'] = $archives;

Response::showTemplate('web/doc/files', $tpl_data);