<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;

defined('IN_IA') or exit('Access Denied');

$mobile = request::str('mobile');
$goods_id = request::int('goods');
$num = request::int('num');
$key = request::str('key');

$access_key = Config::notify('inventory.key', '');
if (empty($access_key) || $key != $access_key) {
    JSON::fail('没有权限访问！');
}

$agent = Agent::findOne(['mobile' => $mobile]);
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$inventory = Inventory::for($agent);
if (!$inventory) {
    JSON::fail('打开用户仓库失败！');
}

if (!$inventory->acquireLocker()) {
    JSON::fail('锁定用户仓库失败！');
}

$goods = Goods::get($goods_id);
if (empty($goods)) {
    JSON::fail('找不到指定的商品！');
}

$result = Util::transactionDo(function () use ($inventory, $goods, $num) {
    $clr = Util::randColor();

    $log = $inventory->stock(null, $goods, $num, [
        'memo' => '第三方接口请求',
        'clr' => $clr,
        'serial' => REQUEST_ID,
    ]);

    if (!$log) {
        throw new RuntimeException('入库失败！');
    }

    return $num;
});

if (is_error($result)) {
    JSON::fail($result);
}

$profile = $goods->profile();
$profile['num'] = $result;

JSON::success([
    'request_id' => REQUEST_ID,
    'goods' => $profile,
]);