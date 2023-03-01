<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$inventory = Inventory::get(Request::int('id'));
if (empty($inventory)) {
    JSON::fail('找不到这个仓库！');
}

$res = $inventory->query(['goods_id' => Request::int('goods')])->findOne();
if (empty($res)) {
    JSON::fail('找不到这个商品库存！');
}

$goods = $res->getGoods();
if (empty($res)) {
    JSON::fail('找不到这个商品！');
}

$content = app()->fetchTemplate('web/inventory/edit_goods', [
    'title' => $inventory->getTitle(),
    'num' => $res->getNum(),
    'goods' => Goods::format($goods, false, true),
]);

JSON::success([
    'title' => '编辑库存商品数量',
    'content' => $content,
]);