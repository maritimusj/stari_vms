<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$keywords = request::trim('keywords', '', true);
$params = [
    'keywords' => $keywords,
];
$result = DeviceTypes::getList($params);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success($result);