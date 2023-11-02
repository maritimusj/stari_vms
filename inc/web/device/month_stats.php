<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\domain\Device;
use zovye\util\CacheUtil;
use zovye\util\Util;

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$month_str = Request::str('month');
try {
    $month = new DateTime($month_str);
} catch (Exception $e) {
    JSON::fail('时间不正确！');
    exit();
}

$title = $month->format('Y年n月');

Response::templateJSON(
    'web/common/stats',
    $title,
    [
        'chartId' => Util::random(10),
        'title' => $title,
        'chart' => CacheUtil::cachedCall(30, function () use ($device, $month, $title) {
            return Stats::chartDataOfMonth($device, $month, "设备：{$device->getName()}($title)", $device->isFuelingDevice() ? function ($val) {
                return $val / 100;
            } : null);
        }, $device->getId(), $month),
    ]
);