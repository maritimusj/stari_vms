<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\inventoryModelObj;

$query = Inventory::query();
//搜索关键字
$keywords = request::trim('keywords');
if ($keywords) {
    $query->whereOr([
        'title LIKE' => "%$keywords%",
    ]);
}

$query->limit(100)->orderBy('id DESC');

$result = [];

/** @var inventoryModelObj $entry */
foreach ($query->findAll() as $entry) {
    $result[] = $entry->format();
}

JSON::success($result);
