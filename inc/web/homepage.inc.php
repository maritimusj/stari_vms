<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$op = request::op('default');

if ($op == 'stats') {

    $rows = [
        'n' => ['title' => '订单数量', 'unit' => '单'],
        'f' => ['title' => '净增用户', 'unit' => '人'],
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
    $device_stat = [
        'on' => 0,
        'off' => 0,
    ];

    $all_device = Device::query()->count();
    $time_less_15 = new DateTime('-15 min');
    $power_time = $time_less_15->getTimestamp();

    $device_stat['on'] = Device::query('last_ping IS NOT NULL AND last_ping > ' . $power_time)->count();
    $device_stat['off'] = $all_device - $device_stat['on'];

    $content = $this->fetchTemplate(
        'web/home/stats',
        [
            'stats_titles' => $stats_titles,
            'rows' => $rows,
            'data' => $data,
            'device_stat' => $device_stat,
        ]
    );

    JSON::success(['content' => $content]);

} elseif ($op == 'agents_chartdata') {

    $n = request::int('n', 10);
    $data = Stats::chartDataOfAgents($n);

    $content = $this->fetchTemplate(
        'web/home/chart',
        [
            'chartid' => Util::random(10),
            'data' => $data,
        ]
    );

    JSON::success(['content' => $content]);

} elseif ($op == 'devices_chartdata') {

    $n = request::int('n', 10);
    $data = Stats::chartDataOfDevices($n);

    $content = $this->fetchTemplate(
        'web/home/chart',
        [
            'chartid' => Util::random(10),
            'data' => $data,
        ]
    );

    JSON::success(['content' => $content]);

} elseif ($op == 'accounts_chartdata') {

    $n = request('n', 10);
    $data = Stats::chartDataOfAccounts($n);

    $content = $this->fetchTemplate(
        'web/home/chart',
        [
            'chartid' => Util::random(10),
            'data' => $data,
        ]
    );

    JSON::success(['content' => $content]);
}

$this->showTemplate('web/home/default', [
    'url' => Util::url('homepage'),
]);
