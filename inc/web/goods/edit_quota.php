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
    'web/goods/quota',
    [
        'goods' => Goods::format($goods),
        'quota_str' => json_encode($goods->getQuota()),
    ]
);

JSON::success([
    'title' => '设置限额',
    'content' => $content,
]);