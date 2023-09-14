<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;
use zovye\domain\Inventory;
use zovye\util\DBUtil;
use zovye\util\Util;

$inventory = Inventory::get(Request::int('id'));
if (empty($inventory)) {
    JSON::fail('找不到这个仓库！');
}

$goods = $inventory->query(['goods_id' => Request::int('goods')])->findOne();
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

if (!$inventory->acquireLocker()) {
    JSON::fail('锁定仓库失败！');
}

$result = DBUtil::transactionDo(function () use ($inventory, $goods) {
    $clr = Util::randColor();

    if ($goods->getNum() != 0) {
        $log = $inventory->stock(null, $goods->getGoods(), 0 - $goods->getNum(), [
            'memo' => '管理员删除商品库存',
            'clr' => $clr,
            'serial' => REQUEST_ID,
        ]);

        if (!$log) {
            throw new RuntimeException('保存库存失败！');
        }
    }

    $goods->destroy();

    return true;
});

if (is_error($result)) {
    JSON::fail($result['message']);
}

JSON::success('库存商品已删除！');