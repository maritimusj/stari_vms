<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$group_id = request::int('id');

$group = Group::query(Group::CHARGING)->findOne(['id' => $group_id]);
if (!$group) {
    JSON::fail('分组不存在！');
}

$title = $group->getTitle();

Response::templateJSON(
    'web/common/stats',
    '',
    [
        'chartId' => Util::random(10),
        'title' => $title,
        'chart' => Stats::monthChartOfChargingGroup($group, $title),
    ]
);