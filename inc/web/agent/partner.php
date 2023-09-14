<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Agent;
use zovye\model\userModelObj;
use zovye\util\Util;

$id = Request::int('id');
$agent = Agent::get($id);
if (empty($agent)) {
    Response::toast('找不到这个代理商！', Util::url('agent'), 'error');
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

Response::showTemplate('web/agent/partner', [
    'op' => 'partner',
    'id' => $id,
    'agent' => $agent,
    'level' => $level,
    'partners' => $result,
]);