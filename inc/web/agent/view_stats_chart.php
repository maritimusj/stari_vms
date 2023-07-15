<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$agent = Agent::get(Request::int('id'));
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$day = DateTime::createFromFormat('Y年m月', Request::trim('month'));
if (!$day) {
    $day = new DateTime();
}

$title = $day->format('Y年n月');

Response::templateJSON(
    'web/agent/stats',
    '',
    [
        'chartId' => Util::random(10),
        'title' => $title,
        'chart' => CacheUtil::cachedCall(30, function () use ($agent, $day, $title) {
            return Stats::chartDataOfMonth($agent, $day, "代理商：{$agent->getName()}($title)");
        }, $agent->getId(), $title),
    ]
);