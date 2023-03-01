<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$params = [];

$goods_id = Request::int('id');
if ($goods_id > 0) {
    $params['goods'] = Goods::data($goods_id, ['detail']);
    if (empty($params['goods'])) {
        Util::itoast('找不到这个商品！', '', 'error');
    }

    if ($params['goods']['name_original']) {
        $params['goods']['name'] = $params['goods']['name_original'];
    }

    if ($params['goods']['type'] == Goods::FlashEgg) {
        app()->showTemplate('web/goods/edit_flash_egg', $params);
    }
}

$type = Request::str('type');
if ($type == Goods::Lottery) {
    app()->showTemplate('web/goods/edit_lottery', $params);
} elseif ($type == Goods::Fueling) {
    app()->showTemplate('web/goods/edit_fueling', $params);
} else {
    app()->showTemplate('web/goods/edit', $params);
}