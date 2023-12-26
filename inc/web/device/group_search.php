<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Agent;
use zovye\domain\Group;
use zovye\model\device_groupsModelObj;

$query = Group::query(Group::NORMAL);

$keyword = Request::trim('keywords');
if ($keyword) {
    $query->where(['title REGEXP' => $keyword]);
}

$agent_id = Request::int('agent');

if ($agent_id) {
    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }
    $query->where(['agent_id' => $agent_id]);
}

$result = [];
/** @var device_groupsModelObj $group */
foreach ($query->findAll() as $group) {
    $data = [
        'id' => $group->getId(),
        'title' => $group->getTitle(),
        'clr' => $group->getClr(),
        'createtime' => date('Y-m-d H:i', $group->getCreatetime()),
    ];
    $agent = $group->getAgent();
    if ($agent) {
        $data['agent'] = $agent->profile();
    }
    $result[] = $data;
}

JSON::success($result);