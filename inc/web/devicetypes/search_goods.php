<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$keywords = request::trim('keywords', '', true);
if (empty($keywords)) {
    $res = Goods::getList(['page' => 1, 'pagesize' => 100, 'default_goods' => true]);
} else {
    $params = [
        'keywords' => $keywords,
        'page' => 1,
        'pagesize' => 100,
    ];
    $res = Goods::getList($params);
}

$id = request::trim('id');
$res['id'] = $id;

JSON::success($res);