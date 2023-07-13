<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('id');
$agent = Agent::get($agent_id);

if (empty($agent)) {
    Response::itoast('找不到这个代理商！', '', 'error');
}
app()->showTemplate('web/agent/stats_view', [
    'agent' => $agent,
]);