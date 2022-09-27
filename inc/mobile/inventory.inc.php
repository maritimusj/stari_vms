<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$mobile = request::str('mobile');
$goods_id = request::int('goods');
$num = request::int('num');

$agent = Agent::findOne(['mobile' => $mobile]);
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$inventory = Inventory::for($agent);
if ($inventory) {
    JSON::fail('打开用户仓库失败！');
}

if (!$inventory->acquireLocker()) {
    JSON::fail('锁定用户仓库失败！');
}

