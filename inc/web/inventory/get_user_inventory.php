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

$user_id = Request::int('user_id');
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