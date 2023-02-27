<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\inventoryModelObj;

$page = max(1, request::int('page'));
$page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Inventory::query();

//搜索关键字
$keywords = request::trim('keywords');
if ($keywords) {
    $query->whereOr([
        'title LIKE' => "%$keywords%",
    ]);
}

$total = $query->count();
$inventories = [
    'page' => 0,
    'total' => 0,
    'totalpage' => 0,
    'list' => [],
];

$pager = '';

if ($total > 0) {
    $total_page = ceil($total / $page_size);

    $pager = We7::pagination($total, $page, $page_size);

    $inventories['total'] = $total;
    $inventories['page'] = $page;
    $inventories['totalpage'] = $total_page;

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    /** @var inventoryModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $inventories['list'][] = $entry->format();
    }
}

if (request::is_ajax()) {
    $content = app()->fetchTemplate(
        'web/inventory/choose',
        [
            'pager' => $pager,
            's_keywords' => $keywords,
            'list' => $inventories['list'],
        ]
    );

    JSON::success(['title' => "库存列表", 'content' => $content]);
}

app()->showTemplate('web/inventory/default', [
    'pager' => $pager,
    'inventories' => $inventories,
]);
