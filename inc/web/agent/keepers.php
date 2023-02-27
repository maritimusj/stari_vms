<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\keeperModelObj;

$id = request::int('id');

$agent = Agent::get($id);
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$query = Keeper::query(['agent_id' => $agent->getId()]);

$result = [];
/** @var keeperModelObj $keeper */
foreach ($query->findAll() as $keeper) {
    $user = $keeper->getUser();
    $result[] = [
        'user' => empty($user) ? [] : $user->profile(),
        'name' => $keeper->getName(),
        'mobile' => $keeper->getMobile(),
        'devices_total' => intval($keeper->deviceQuery()->count()),
        'createtime' => date('Y-m-d H:i:s', $keeper->getCreatetime()),
    ];
}

app()->showTemplate(
    'web/agent/keepers',
    [
        'agent' => $agent->profile(),
        'list' => $result,
        'back_url' => Util::url('agent', []),
    ]
);