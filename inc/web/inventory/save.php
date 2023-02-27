<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\inventoryModelObj;

$id = request::int('id');
if ($id > 0) {
    /** @var inventoryModelObj $inventory */
    $inventory = Inventory::get($id);
    if (empty($inventory)) {
        Util::itoast('找不到指定的仓库！', '', 'error');
    }
    $inventory->setTitle(request::trim('title'));
    $inventory->save();
    Util::itoast('保存成功！', '', 'success');
}

$data = [];
$data['title'] = request::trim('title');

$user_id = request::int('userId');
if ($user_id > 0) {
    $user = User::get($user_id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', '', 'error');
    }
    $uid = Inventory::getUID($user);
    if (Inventory::exists($uid)) {
        Util::itoast('仓库已经存在！', '', 'error');
    }
    $data['uid'] = $uid;
    $data['extra'] = [
        'user' => $user->profile(),
    ];
}

$parent_inventory_id = request::int('parentId');
if ($parent_inventory_id > 0) {
    $parent_inventory = Inventory::get($parent_inventory_id);
    if (empty($parent_inventory)) {
        Util::itoast('找不到指定的仓库！', '', 'error');
    }
    $data['parent_id'] = $parent_inventory->getId();
}

$inventory = Inventory::create($data);
if ($inventory) {
    Util::itoast('创建成功！', '', 'success');
}

Util::itoast('创建失败！', '', 'error');