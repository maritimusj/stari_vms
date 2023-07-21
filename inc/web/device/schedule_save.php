<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$delay = Request::int('delay');

$serial = Util::random(16, true);

$device->updateSettings('schedule', [
    'delay' => $delay,
    'serial' => $serial,
]);

if ($delay > 0) {
    $url = Util::murl('device', [
        'op' => 'schedule',
    ]);

    $result = CtrlServ::httpCallback($url, 'normal', Request::bool('now') ? 0 : $delay, $delay, json_encode([
        'serial' => $serial,
        'device' => $device->getId(),
    ]));

    if (is_error($result)) {
        JSON::fail($result);
    }

    $device->updateSettings('schedule.job.uid', $result['data']['jobUID']);
}

JSON::success('保存成功！');