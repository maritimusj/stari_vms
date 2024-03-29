<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Goods;

defined('IN_IA') or exit('Access Denied');

$params = [];

$goods_id = Request::int('id');
if ($goods_id > 0) {
    $params['goods'] = Goods::data($goods_id, ['detail' => true]);
    if (empty($params['goods'])) {
        Response::toast('找不到这个商品！', '', 'error');
    }

    if ($params['goods']['name_original']) {
        $params['goods']['name'] = $params['goods']['name_original'];
    }

    if ($params['goods']['type'] == 'flash_egg') {
        Response::showTemplate('web/goods/edit_flash_egg', $params);
    }
}

$type = Request::str('type');
if ($type == Goods::Lottery) {
    Response::showTemplate('web/goods/edit_lottery', $params);
} elseif ($type == Goods::Ts) {
    Response::showTemplate('web/goods/edit_ts', $params);
} elseif ($type == Goods::Fueling) {
    Response::showTemplate('web/goods/edit_fueling', $params);
} else {
    Response::showTemplate('web/goods/edit', $params);
}