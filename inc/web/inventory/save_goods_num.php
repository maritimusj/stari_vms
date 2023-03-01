<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;

$inventory = Inventory::get(Request::int('id'));
if (empty($inventory)) {
    JSON::fail('找不到这个仓库！');
}

$goods = Goods::get(Request::int('goods'));
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

$num = Request::int('num');

if (!$inventory->acquireLocker()) {
    JSON::fail('锁定仓库失败！');
}

$result = Util::transactionDo(function () use ($inventory, $goods, $num) {
    $clr = Util::randColor();

    $inventory_goods = $inventory->query(['goods_id' => $goods->getId()])->findOne();
    if (!empty($inventory_goods)) {
        $num = $num - $inventory_goods->getNum();
    }

    $log = $inventory->stock(null, $goods, $num, [
        'memo' => '管理员编辑商品库存',
        'clr' => $clr,
        'serial' => REQUEST_ID,
    ]);

    if (!$log) {
        throw new RuntimeException('入库失败！');
    }

    return $num;
});

if (is_error($result)) {
    JSON::fail($result['message']);
}

JSON::success([
    'msg' => '库存保存成功！',
    'num' => $result > 0 ? "+$result" : $result,
]);
