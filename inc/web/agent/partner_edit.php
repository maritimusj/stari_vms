<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Agent;
use zovye\domain\User;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('agentid');
$user_id = Request::int('partnerid') ?: Request::int('userid');

$back_url = Util::url('agent', ['op' => 'partner', 'id' => $agent_id]);

$agent = Agent::get($agent_id);
if (empty($agent)) {
    Response::toast('找不到这个代理商！', Util::url('agent'), 'error');
}

$level = $agent->getAgentLevel();

$user = User::get($user_id);
if (empty($user)) {
    Response::toast('找不到这个用户！', Util::url('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
}

$partner_data = $user->get('partnerData', []);
$agent_data = $agent->getAgentData();

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
]);