<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\authAccount;

use zovye\Account;
use zovye\Agent;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\request;

$agent_id = request::str('agent');
$accountUID = request::str('account');
$total = request::int('total');

$params = [
    'agent' => $agent_id,
    'account' => $accountUID,
    'total' => $total,
];

$op = request::op('default');
if ($op == 'auth_account' && CtrlServ::checkJobSign($params)) {

    $acc = Account::findOneFromUID($accountUID);
    if ($acc) {
        /** @var agentModelObj $agent_id */
        $agent = Agent::get($agent_id);
        if ($agent) {
            $acc->setAgentId($agent->getId());
            $acc->save();
            $params['account'] = $acc->format();
        } else {
            $params['error'] = '找不到这个代理商！';
        }
    } else {
        $params['error'] = '找不到这个公众号或者公众号还没有创建！';
        if ($total < 60) {
            Job::authAccount($agent_id, $accountUID, $total + 1);
        }
    }
}

Log::debug('auth_account', $params);