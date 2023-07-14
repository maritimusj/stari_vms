<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$user = User::get(Request::int('id'));
$year = Request::str('year', (new DateTime())->format('Y'));

list($years, $data) = Stats::getUserMonthCommissionStatsOfYear($user, $year);

Response::templateJSON(
    'web/user/month_stats', "<b>{$user->getName()}</b>的收入统计",
    [
        'data' => $data,
        'years' => $years && count($years) > 1 ? $years : [],
        'current' => $year,
        'user_id' => $user->getId(),
    ]
);