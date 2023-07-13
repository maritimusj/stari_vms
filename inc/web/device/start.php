<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$device = Device::get($id);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$chargerID = Request::int('chargerID');

$res = DeviceUtil::test(null, $device, $chargerID);

Response::json(!is_error($res), ['msg' => $res['message']]);