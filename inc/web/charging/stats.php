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

$title = $group->getTitle();

$content = app()->fetchTemplate(
    'web/device/stats',
    [
        'chartid' => Util::random(10),
        'title' => $title,
        'chart' => Stats::chartOfchargingGroup($group, $title),
    ]
);

JSON::success(['title' => '', 'content' => $content]);