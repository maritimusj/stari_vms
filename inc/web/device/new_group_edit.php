<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Group;
use zovye\model\device_groupsModelObj;
use zovye\util\Util;

$id = Request::int('id');
$tpl_data['id'] = $id;

/** @var device_groupsModelObj $group */
$group = Group::get($id);
if (empty($group)) {
    Response::toast('分组不存在！', Util::url('device', ['op' => 'new_group']), 'error');
}

$tpl_data['group'] = [
    'title' => $group->getTitle(),
    'clr' => $group->getClr(),
];

$agent = $group->getAgent();
if (!empty($agent)) {
    $tpl_data['agent'] = [
        'id' => $agent->getId(),
        'name' => $agent->getName(),
        'mobile' => $agent->getMobile(),
    ];
}

Response::showTemplate('web/device/new_group_edit', $tpl_data);