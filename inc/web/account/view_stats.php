<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;

$id = Request::int('id');

$account = Account::get($id);
if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

$title = $account->getTitle();
$time_str = Request::has('month') ? date('Y-').Request::int('month').date('-01 00:00:00') : 'today';

try {
    $month = new DateTime($time_str);
    $caption = $month->format('Y年n月');
    $data = Stats::chartDataOfMonth($account, $month, "任务：$title($caption)");
} catch (Exception $e) {
}

Response::templateJSON(
    'web/account/stats',
    '',
    [
        'chartId' => Util::random(10),
        'chart' => $data ?? [],
    ]
);