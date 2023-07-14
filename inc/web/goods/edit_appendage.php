<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$goods = Goods::get(request('id'));
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

Response::templateJSON(
    'web/goods/appendage',
    '附加信息',
    [
        'goods' => Goods::format($goods),
        'appendage' => $goods->getAppendage(),
    ]
);