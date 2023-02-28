<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$params = [
    'page' => Request::int('page'),
    'pagesize' => Request::int('pagesize'),
    'keywords' => Request::trim('keywords', '', true),
    'default_goods' => false,
];

$result = Goods::getList($params);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success($result);