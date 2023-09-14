<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\DeviceTypes;

defined('IN_IA') or exit('Access Denied');

$keywords = Request::trim('keywords', '', true);
$params = [
    'keywords' => $keywords,
];
$result = DeviceTypes::getList($params);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success($result);