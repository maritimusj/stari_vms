<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\business\ChargingServ;
use zovye\domain\Group;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$groups = [];
if (Request::has('id')) {
    $id = Request::int('id');
    $group = Group::get($id, Group::CHARGING);
    if (!$group) {
        Response::toast('找不到这个分组！', Util::url('charging'), 'error');
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

Response::toast('已更新！', Util::url('charging'), 'success');