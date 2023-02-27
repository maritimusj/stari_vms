<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$groups = [];
if (request::has('id')) {
    $id = request::int('id');
    $group = Group::get($id, Group::CHARGING);
    if (!$group) {
        Util::itoast('找不到这个分组！', Util::url('charging'), 'error');
    }
    $groups[] = $group;
} else {
    foreach (Group::query(Group::CHARGING)->findAll() as $group) {
        $groups[] = $group;
    }
}

foreach ($groups as $group) {
    $res = ChargingServ::createOrUpdateGroup($group);
    if (is_error($res)) {
        Log::error('group', $res);
        continue;
    }
    $group->setVersion($res);
    $group->save();
}

Util::itoast('已更新！', Util::url('charging'), 'success');