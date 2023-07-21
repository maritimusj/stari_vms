<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (!App::isDeviceScheduleEnabled()) {
    JSON::fail('功能没有启用！');
}

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$delay = Request::int('delay');

$result = Device::setSchedule($device, Request::bool('now') ? 0 : $delay, $delay);
if (is_error($result)) {
    JSON::fail($result);
}

JSON::success('保存成功！');