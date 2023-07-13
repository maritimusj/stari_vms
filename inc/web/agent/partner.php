<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

$id = Request::int('id');
$agent = Agent::get($id);
if (empty($agent)) {
    Response::itoast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
}

$level = $agent->getAgentLevel();
$partners = Agent::getAllPartners($agent);

$result = [];
/** @var userModelObj $partner */
foreach ($partners as $partner) {
    $result[] = [
        'id' => $partner->getId(),
        'nickname' => $partner->getNickname(),
        'avatar' => $partner->getAvatar(),
        'name' => $partner->getName() ?: '&lt;未登记&gt;',
        'mobile' => $partner->getMobile(),
        'createtime' => date('Y-m-d H:i:s', $partner->getCreatetime()),
    ];
}

app()->showTemplate('web/agent/partner', [
    'op' => 'partner',
    'id' => $id,
    'agent' => $agent,
    'level' => $level,
    'partners' => $result,
]);