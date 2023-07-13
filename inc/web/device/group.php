<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_groupsModelObj;

$query = Group::query(Group::NORMAL);

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$total = $query->count();
$total_page = ceil($total / $page_size);

$query->page($page, $page_size);

$keywords = Request::trim('keywords');
if ($keywords) {
    $query->where(['title REGEXP' => $keywords]);
}

//分配assign.js通过ids获取对应分组数据
$ids = Util::parseIdsFromGPC();
if (!empty($ids)) {
    $query->where(['id' => $ids]);
}

$result = [
    'page' => $page,
    'total' => $total,
    'totalpage' => $total_page,
    'list' => [],
];

/** @var device_groupsModelObj $entry */
foreach ($query->findAll() as $entry) {
    $result['list'][] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'clr' => $entry->getClr(),
        'total' => Device::query(['group_id' => $entry->getId()])->count(),
    ];
}

$result['serial'] = Request::trim('serial') ?: microtime(true).'';

Response::json(true, $result);