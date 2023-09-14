<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Agent;
use zovye\domain\Keeper;
use zovye\model\keeperModelObj;
use zovye\util\Util;

$id = Request::int('id');

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
        'id' => $keeper->getId(),
        'user' => empty($user) ? [] : $user->profile(),
        'name' => $keeper->getName(),
        'mobile' => $keeper->getMobile(),
        'devices_total' => intval($keeper->deviceQuery()->count()),
        'createtime' => date('Y-m-d H:i:s', $keeper->getCreatetime()),
    ];
}

Response::showTemplate(
    'web/agent/keepers',
    [
        'agent' => $agent->profile(),
        'list' => $result,
        'back_url' => Util::url('agent'),
    ]
);