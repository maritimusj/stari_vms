<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('agentid');
$user_id = Request::int('userid');

$back_url = $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]);

if ($agent_id == $user_id) {
    Response::itoast('合伙人不能是自己！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

$agent = Agent::get($agent_id);

$level = $agent->getAgentLevel();
$user = User::get($user_id);
if (empty($user)) {
    Response::itoast('找不到这个用户！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

if ($user->isAgent() || $user->isPartner()) {
    Response::itoast('该用户已经是代理商或合伙人！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

$partner_data['mobile'] = $user->getMobile();

app()->showTemplate('web/agent/partner_edit', [
    'op' => 'partnerAdd',
    'agent_id' => $agent_id,
    'user_id' => $user_id,
    'back_url' => $back_url,
    'agent' => $agent,
    'level' => $level,
    'user' => $user,
    'partnerData' => $partner_data,
]);