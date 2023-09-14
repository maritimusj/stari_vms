<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Group;

defined('IN_IA') or exit('Access Denied');

$group_id = request::int('id');

$group = Group::query(Group::CHARGING)->findOne(['id' => $group_id]);
if (!$group) {
    JSON::fail('分组不存在！');
}

$title = $group->getTitle();

Response::templateJSON(
    'web/device/select_day_stats',
    $title,
    [
        'id' => $group->getId(),
    ]
);