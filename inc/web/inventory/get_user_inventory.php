<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\inventoryModelObj;

$user_id = request::int('user_id');
$user = User::get($user_id);
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}
/** @var inventoryModelObj $inventory */
$inventory = Inventory::find($user);
if (empty($inventory)) {
    JSON::fail('找不到指定的仓库！');
}

JSON::success([
    'id' => $inventory->getId(),
    'uid' => $inventory->getUid(),
    'title' => $inventory->getTitle(),
    'createtime' => $inventory->getCreatetime(),
    'createtime_formatted' => date('Y-m-d H:i:s', $inventory->getCreatetime()),
]);