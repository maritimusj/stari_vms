<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$params = [
    'page' => request::int('page'),
    'pagesize' => request::int('pagesize'),
    'keywords' => request::trim('keywords', '', true),
    'default_goods' => false,
];

$result = Goods::getList($params);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success($result);