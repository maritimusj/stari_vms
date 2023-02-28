<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$device = Device::get($id);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$chargerID = Request::int('chargerID');

$res = Util::deviceTest(null, $device, $chargerID);

Util::resultJSON(!is_error($res), ['msg' => $res['message']]);