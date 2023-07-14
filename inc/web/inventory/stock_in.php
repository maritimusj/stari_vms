<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$inventory = Inventory::get(Request::int('id'));
if (empty($inventory)) {
    Response::toast('找不到这个仓库！', '', 'error');
}

$tpl_data = [
    'id' => $id,
    'user' => Request::int('user'),
    'title' => $inventory->getTitle(),
];

Response::showTemplate('web/inventory/stock_in', $tpl_data);