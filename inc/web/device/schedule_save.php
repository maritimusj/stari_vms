<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isDeviceScheduleTaskEnabled()) {
    JSON::fail('功能没有启用！');
}

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$date = Request::trim('date');
$time = Request::trim('time');

$result = Device::setScheduleTask($device, $date, $time);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success('保存成功！');