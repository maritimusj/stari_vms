<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$goods = Goods::get(request('id'));
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

$content = app()->fetchTemplate(
    'web/goods/appendage',
    [
        'goods' => Goods::format($goods),
        'appendage' => $goods->getAppendage(),
    ]
);

JSON::success([
    'title' => '附加信息',
    'content' => $content,
]);