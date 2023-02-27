<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\vipModelObj;

$id = request::int('id');

$agent = Agent::get($id);
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$query = VIP::query(['agent_id' => $agent->getId()]);

$result = [];
/** @var vipModelObj $vip */
foreach ($query->findAll() as $vip) {
    $user = $vip->getUser();
    $result[] = [
        'id' => $vip->getId(),
        'user' => empty($user) ? [] : $user->profile(),
        'mobile' => $vip->getMobile(),
        'name' => $vip->getName(),
        'devices_total' => $vip->getDevicesTotal(),
        'createtime' => date('Y-m-d H:i:s', $vip->getCreatetime()),
    ];
}

app()->showTemplate(
    'web/agent/vip',
    [
        'agent' => $agent->profile(),
        'list' => $result,
        'back_url' => Util::url('agent', []),
    ]
);