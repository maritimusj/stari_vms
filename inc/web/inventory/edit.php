<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Inventory;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

$id = Request::int('id');
if ($id > 0) {
    $inventory = Inventory::get($id);
    if (empty($inventory)) {
        Response::toast('找不到指定的仓库！', '', 'error');
    }
    $tpl_data['id'] = $id;
    $tpl_data['inventory'] = $inventory;
}

Response::showTemplate('web/inventory/edit', $tpl_data);