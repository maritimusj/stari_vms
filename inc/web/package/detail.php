<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
$package = Package::get($id);
if (empty($package)) {
    JSON::fail('找不到这个套餐！');
}

$result = $package->format(true);
JSON::success($result);