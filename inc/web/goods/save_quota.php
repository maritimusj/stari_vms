<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$params = [];
parse_str(request('params'), $params);

$goods = Goods::get($params['goodsId']);
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

$data = [
    'free' => [
        'day' => intval($params['free-day']),
        'all' => intval($params['free-all']),
    ],
    'pay' => [
        'day' => intval($params['pay-day']),
        'all' => intval($params['pay-all']),
    ],
];

$goods->setQuota($data);
$goods->save();

JSON::success('保存成功！');