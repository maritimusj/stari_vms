<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use Exception;
use zovye\JSON;
use zovye\Util;
use zovye\Stats;
use zovye\Account;

$id = request::int('id');

$acc = Account::get($id);
if (empty($acc)) {
    JSON::fail('找不到这个任务！');
}

$title = $acc->getTitle();
$time_str = request::has('month') ? date('Y-').request::int('month').date('-01 00:00:00') : 'today';

try {
    $month = new DateTime($time_str);
    $caption = $month->format('Y年n月');
    $data = Stats::chartDataOfMonth($acc, $month, "任务：$title($caption)");
} catch (Exception $e) {
}

$content = app()->fetchTemplate(
    'web/account/stats',
    [
        'chartid' => 'chart-'.Util::random(10),
        'chart' => $data ?? [],
    ]
);

JSON::success(['title' => '', 'content' => $content]);