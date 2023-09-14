<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\authAccount;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\domain\Account;
use zovye\domain\Agent;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\Request;

$agent_id = Request::str('agent');
$account_uid = Request::str('account');
$total = Request::int('total');

$log = [
    'agent' => $agent_id,
    'account' => $account_uid,
    'total' => $total,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$account = Account::findOneFromUID($account_uid);
if ($account) {
    /** @var agentModelObj $agent_id */
    $agent = Agent::get($agent_id);
    if ($agent) {
        $account->setAgentId($agent->getId());
        $account->save();
        $log['account'] = $account->format();
    } else {
        $log['error'] = '找不到这个代理商！';
    }
} else {
    $log['error'] = '找不到这个公众号或者公众号还没有创建！';
    if ($total < 60) {
        Job::authAccount($agent_id, $account_uid, $total + 1);
    }
}

Log::debug('auth_account', $log);