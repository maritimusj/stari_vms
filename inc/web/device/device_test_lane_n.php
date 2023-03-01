<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(request('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$lane = max(0, Request::int('lane'));
$res = Util::deviceTest(null, $device, $lane);

Util::resultJSON(!is_error($res), ['msg' => $res['message']]);