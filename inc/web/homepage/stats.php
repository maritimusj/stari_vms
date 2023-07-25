<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$data = CacheUtil::cachedCall(30, function () {
    $rows = [
        'n' => ['title' => App::isChargingDeviceEnabled() || App::isFuelingDeviceEnabled() ? '订单数量' : '出货数量', 'unit' => ''],
        'f' => ['title' => '净增用户', 'unit' => ''],
    ];

    $stats_titles = [
        'today' => '今日',
        'yesterday' => '昨日',
        'last7days' => '近7日',
        'month' => '本月',
        'lastmonth' => '上月',
        'all' => '全部',
    ];

    $data = Stats::brief();
    $device_stats = [];

    $all_device = Device::query()->count();
    $time_less_15 = new DateTime('-15 min');
    $ts = $time_less_15->getTimestamp();

    $device_stats['on'] = Device::query('last_online IS NOT NULL AND last_online > '.$ts)->count();
    $device_stats['off'] = $all_device - $device_stats['on'];

    return [
        'stats_titles' => $stats_titles,
        'rows' => $rows,
        'data' => $data,
        'device_stats' => $device_stats,
    ];
});

Response::templateJSON('web/home/stats', '', $data);