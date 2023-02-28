<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

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
$content = app()->fetchTemplate(
    'web/agent/stats',
    [
        'chartid' => Util::random(10),
        'title' => $title,
        'chart' => Util::cachedCall(30, function () use ($agent, $day, $title) {
            return Stats::chartDataOfMonth($agent, $day, "代理商：{$agent->getName()}($title)");
        }, $agent->getId(), $title),
    ]
);

JSON::success(['title' => '', 'content' => $content]);