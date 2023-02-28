<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\repair;

use DateTime;
use Exception;
use zovye\Agent;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\OrderCounter;
use zovye\Request;

$op = Request::op('default');
$data = [
    'agent' => Request::int('agent'),
    'month' => Request::str('month'),
];

$log = [
    'params' => $data,
];

if ($op == 'repair' && CtrlServ::checkJobSign($data)) {
    $agent = Agent::get($data['agent']);
    if (empty($agent)) {
        $log['error'] = '找不到这个代理商！';
        writeLogAndExit($log);
    }

    $agent->updateSettings('repair', [
        'status' => 'finished',
        'time' => time(),
        'used' => $used ?? 0,
    ]);

} else {
    $log['error'] = '参数或签名错误！';
}

writeLogAndExit($log);

function writeLogAndExit($log)
{
    Log::debug('repair', $log);
    Job::exit();
}
