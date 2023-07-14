<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('agentid');
$user_id = Request::int('partnerid') ?: Request::int('userid');

$back_url = $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]);

$agent = Agent::get($agent_id);
if (empty($agent)) {
    Response::toast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
}

$level = $agent->getAgentLevel();

$user = User::get($user_id);
if (empty($user)) {
    Response::toast('找不到这个用户！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

$partner_data = $user->get('partnerData', []);

$agent_data = $agent->getAgentData();
$notice = $agent_data['partners'][$user->getId()]['notice'] ?: [];

Response::showTemplate('web/agent/partner_edit', [
    'op' => 'partnerEdit',
    'agent_id' => $agent_id,
    'user_id' => $user_id,
    'back_url' => $back_url,
    'agent' => $agent,
    'level' => $level,
    'user' => $user,
    'partnerData' => $partner_data,
    'agentData' => $agent_data,
    'notice' => $notice,
]);