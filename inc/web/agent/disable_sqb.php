<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Agent;
use zovye\domain\PaymentConfig;

defined('IN_IA') or exit('Access Denied');

$agent = Agent::get(Request::int('id'));

if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

if (PaymentConfig::remove([
    'agent_id' => $agent->getId(),
    'name' => Pay::SQB,
])) {
    JSON::success('成功！');
}

JSON::fail('失败！');