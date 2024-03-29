<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$res = $device->getOnlineDetail(false);
if (is_error($res)) {
    JSON::fail($res);
}

if (empty($res)) {
    JSON::fail('请求出错，请稍后再试！');
}

JSON::success($res);