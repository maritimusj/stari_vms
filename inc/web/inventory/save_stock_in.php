<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;

$user = User::get(Request::int('userid'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$inventory = Inventory::for($user);
if (empty($inventory)) {
    JSON::fail('找不到用户的仓库！');
}

if (!$inventory->acquireLocker()) {
    JSON::fail('锁定仓库失败！');
}

$result = Util::transactionDo(function () use ($inventory) {

    $user_ids = Request::array('user');
    $goods_ids = Request::array('goods');
    $num_arr = Request::array('num');

    $logs = [];

    $clr = Util::randColor();
    foreach ($goods_ids as $index => $goods_id) {
        if (empty($goods_id)) {
            continue;
        }
        $goods = Goods::get($goods_id);
        if (empty($goods)) {
            throw new RuntimeException('找不到这个商品！');
        }
        $num = isset($num_arr[$index]) ? intval($num_arr[$index]) : 0;
        if ($num == 0) {
            continue;
        }

        $src_inventory = null;
        $user_id = isset($user_ids[$index]) ? intval($user_ids[$index]) : 0;
        if (!empty($user_id)) {
            $from = User::get($user_id);
            if (empty($from)) {
                throw new RuntimeException('找不到源用户！');
            }
            $src_inventory = Inventory::for($from);
            if (empty($src_inventory)) {
                throw new RuntimeException('找不到源用户仓库！');
            }
            $l = $src_inventory->acquireLocker();
            if (empty($l)) {
                throw new RuntimeException('锁定源仓库失败！');
            }
            $log = $src_inventory->stock($inventory, $goods, -$num, [
                'memo' => '管理员后台调货',
                'clr' => $clr,
                'serial' => REQUEST_ID,
            ]);
            if (!$log) {
                throw new RuntimeException('调货失败！');
            }
        }

        $log = $inventory->stock($src_inventory, $goods, $num, [
            'memo' => '管理员后台入库',
            'clr' => $clr,
            'serial' => REQUEST_ID,
        ]);

        if (!$log) {
            throw new RuntimeException('入库失败！');
        }

        $logs[] = $log;
    }

    return $logs;
});

if (is_error($result)) {
    JSON::fail($result['message']);
}

if (empty($result)) {
    JSON::fail('没有指定商品或者商品数量！');
}
JSON::success('入库成功！');