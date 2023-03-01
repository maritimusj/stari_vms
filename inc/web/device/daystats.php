<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$device = Device::get(request('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$title = date('n月d日');
$content = app()->fetchTemplate(
    'web/device/stats',
    [
        'chartid' => Util::random(10),
        'title' => $title,
        'chart' => Util::cachedCall(30, function () use ($device, $title) {
            return Stats::chartDataOfDay($device, new DateTime(), "设备：{$device->getName()}($title)", $device->isFuelingDevice() ? function ($val) {
                return $val / 100;
            } : null);
        }, $device->getId()),
    ]
);

JSON::success(['z' => date('z'), 'content' => $content]);