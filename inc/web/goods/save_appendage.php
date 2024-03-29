<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Goods;

defined('IN_IA') or exit('Access Denied');

$params = [];
parse_str(Request::str('params'), $params);

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