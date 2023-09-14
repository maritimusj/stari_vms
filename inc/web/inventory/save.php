<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Inventory;
use zovye\domain\User;
use zovye\model\inventoryModelObj;

$id = Request::int('id');
if ($id > 0) {
    /** @var inventoryModelObj $inventory */
    $inventory = Inventory::get($id);
    if (empty($inventory)) {
        Response::toast('找不到指定的仓库！', '', 'error');
    }
    $inventory->setTitle(Request::trim('title'));
    $inventory->save();
    Response::toast('保存成功！', '', 'success');
}

$data = [];
$data['title'] = Request::trim('title');

$user_id = Request::int('userId');
if ($user_id > 0) {
    $user = User::get($user_id);
    if (empty($user)) {
        Response::toast('找不到这个用户！', '', 'error');
    }
    $uid = Inventory::getUID($user);
    if (Inventory::exists($uid)) {
        Response::toast('仓库已经存在！', '', 'error');
    }
    $data['uid'] = $uid;
    $data['extra'] = [
        'user' => $user->profile(),
    ];
}

$parent_inventory_id = Request::int('parentId');
if ($parent_inventory_id > 0) {
    $parent_inventory = Inventory::get($parent_inventory_id);
    if (empty($parent_inventory)) {
        Response::toast('找不到指定的仓库！', '', 'error');
    }
    $data['parent_id'] = $parent_inventory->getId();
}

$inventory = Inventory::create($data);
if ($inventory) {
    Response::toast('创建成功！', '', 'success');
}

Response::toast('创建失败！', '', 'error');