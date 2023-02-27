<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$vip_id = request::int('vip');
$vip = VIP::get($vip_id);
if (empty($vip)) {
    JSON::fail('找不到这个VIP用户！');
}

$device_id = request::int('id');

$ids = $vip->getDeviceIds();

$ids = array_diff($ids, [$device_id]);
$vip->setDeviceIds($ids);

$vip->save();

JSON::success('删除成功！');