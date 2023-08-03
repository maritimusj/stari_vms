<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\authAccount;

defined('IN_IA') or exit('Access Denied');

use zovye\Account;
use zovye\Agent;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\Request;

$agent_id = Request::str('agent');
$account_uid = Request::str('account');
$total = Request::int('total');

$params = [
    'agent' => $agent_id,
    'account' => $account_uid,
    'total' => $total,
];

$op = Request::op('default');
if ($op == 'auth_account' && CtrlServ::checkJobSign($params)) {

    $acc = Account::findOneFromUID($account_uid);
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
            Job::authAccount($agent_id, $account_uid, $total + 1);
        }
    }
}

Log::debug('auth_account', $params);