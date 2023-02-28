<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');

$device = Device::get($id);
if (!$device) {
    JSON::fail('找不到这个设备！');
}

$iccid = $device->getICCID();
if (!$iccid) {
    JSON::fail('没有ICCID');
}

$result = CtrlServ::getV2("iccid/$iccid");
if (is_error($result)) {
    JSON::fail('查询失败');
}

JSON::success($result);