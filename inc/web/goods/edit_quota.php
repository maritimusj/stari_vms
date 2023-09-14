<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Goods;

defined('IN_IA') or exit('Access Denied');

$goods = Goods::get(Request::int('id'));
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

Response::templateJSON(
    'web/goods/quota',
    '设置限额',
    [
        'goods' => Goods::format($goods),
        'quota_str' => json_encode($goods->getQuota()),
    ]
);