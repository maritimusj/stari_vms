<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$goods = Goods::get(request('id'));
if ($goods && $goods->getType() !== Goods::FlashEgg) {

    if (Goods::safeDelete($goods)) {
        JSON::success('商品删除成功！');
    }
}

JSON::fail('商品删除失败！');