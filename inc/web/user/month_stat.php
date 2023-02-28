<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;

$user = User::get(request::int('id'));
$year = request::str('year', (new DateTime())->format('Y'));

list($years, $data) = Stats::getUserMonthCommissionStatsOfYear($user, $year);

$content = app()->fetchTemplate(
    'web/user/month_stat',
    [
        'data' => $data,
        'years' => $years && count($years) > 1 ? $years : [],
        'current' => $year,
        'user_id' => $user->getId(),
    ]
);

JSON::success(['title' => "<b>{$user->getName()}</b>的收提统计", 'content' => $content]);