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
    'mfrs' => trim($params['mfrs']),
    'tel' => trim($params['tel']),
    'lot' => trim($params['lot']),
    'spec' => trim($params ['spec']),
    'exp' => trim($params['exp']),
];

$goods->setAppendage($data);
$goods->save();

JSON::success('保存成功！');