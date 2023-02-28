<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

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
/** @var device_groupsModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'clr' => $entry->getClr(),
        'createtime' => date('Y-m-d H:i', $entry->getCreatetime()),
    ];
    $agent = $entry->getAgent();
    if ($agent) {
        $data['agent'] = $agent->profile();
    }
    $result[] = $data;
}

JSON::success($result);