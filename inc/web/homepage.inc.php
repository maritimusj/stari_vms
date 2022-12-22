<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$op = request::op('default');

if ($op == 'stats') {

    $data = Util::cachedCall(30, function () {
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
        $device_stat = [];

        $all_device = Device::query()->count();
        $time_less_15 = new DateTime('-15 min');
        $ts = $time_less_15->getTimestamp();

        $device_stat['on'] = Device::query('last_online IS NOT NULL AND last_online > '.$ts)->count();
        $device_stat['off'] = $all_device - $device_stat['on'];

        return [
            'stats_titles' => $stats_titles,
            'rows' => $rows,
            'data' => $data,
            'device_stat' => $device_stat,
        ];
    });

    $content = app()->fetchTemplate('web/home/stats', $data);

    JSON::success(['content' => $content]);

} elseif ($op == 'agents_chartdata') {

    $content = app()->fetchTemplate(
        'web/home/chart',
        [
            'chartid' => Util::random(10),
            'data' => Util::cachedCall(30, function () {
                $n = request::int('n', 10);

                return Stats::chartDataOfAgents($n);
            }),
        ]
    );

    JSON::success(['content' => $content]);

} elseif ($op == 'devices_chartdata') {

    $content = app()->fetchTemplate(
        'web/home/chart',
        [
            'chartid' => Util::random(10),
            'data' => Util::cachedCall(30, function () {
                $n = request::int('n', 10);

                return Stats::chartDataOfDevices($n);
            }),
        ]
    );

    JSON::success(['content' => $content]);

} elseif ($op == 'accounts_chartdata') {

    $content = app()->fetchTemplate(
        'web/home/chart',
        [
            'chartid' => Util::random(10),
            'data' => Util::cachedCall(30, function () {
                $n = request::int('n', 10);

                return Stats::chartDataOfAccounts($n);
            }),
        ]
    );

    JSON::success(['content' => $content]);
}

app()->showTemplate('web/home/default', [
    'commission_enabled' => App::isCommissionEnabled(),
    'url' => Util::url('homepage'),
]);
