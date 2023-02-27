<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$agent_id = request::int('id');
$agent = Agent::get($agent_id);

if (empty($agent)) {
    Util::itoast('找不到这个代理商！', '', 'error');
}
app()->showTemplate('web/agent/stats_view', [
    'agent' => $agent,
]);