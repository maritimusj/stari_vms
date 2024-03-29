<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Agent;

defined('IN_IA') or exit('Access Denied');

$agent_id = Request::int('id');

$agent = Agent::get($agent_id);

if (empty($agent)) {
    Response::toast('找不到这个代理商！', '', 'error');
}

Response::showTemplate('web/agent/device_stats_view', [
    'agent' => $agent,
]);