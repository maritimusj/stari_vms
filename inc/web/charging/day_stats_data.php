<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$group_id = request::int('id');
if (!$group_id) {
    JSON::fail('分组不存在！');
}

$group = Group::query(Group::CHARGING)->findOne(['id' => $group_id]);
if (!$group) {
    JSON::fail('分组不存在！');
}

$s_date = request::trim('begin');
$e_date = request::trim('end');
if (empty($s_date) || empty($e_date)) {
    JSON::fail('请选择正确的时间！');
}

$title = $group->getTitle();

$chart = Stats::dayChartOfChargingGroup($group, $s_date, $e_date, $title);

$content = app()->fetchTemplate(
    'web/common/stats',
    [
        'chartId' => Util::random(10),
        'chart' => $chart,
    ]
);

JSON::success(['title' => '', 'content' => $content]);