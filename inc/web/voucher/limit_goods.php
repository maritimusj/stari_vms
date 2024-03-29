<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Goods;
use zovye\domain\GoodsVoucher;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$voucher = GoodsVoucher::get($id);
$limitGoodsIds = $voucher->getExtraData('limitGoods', []);

$list = [];
foreach ((array)$limitGoodsIds as $id) {
    $goods = Goods::get($id);
    if (isset($goods)) {
        $list[] = Goods::format($goods, false, true);
    }
}

JSON::success($list);