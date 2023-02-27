<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');

$inventory = Inventory::get(request::int('id'));
if (empty($inventory)) {
    Util::itoast('找不到这个仓库！', '', 'error');
}

$tpl_data = [
    'id' => $id,
    'user' => request::int('user'),
    'title' => $inventory->getTitle(),
];

app()->showTemplate('web/inventory/stock_in', $tpl_data);