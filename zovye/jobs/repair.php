<?php

namespace zovye\job\repair;

use zovye\Agent;
use zovye\CtrlServ;
use zovye\Job;
use zovye\request;
use zovye\Util;
use function zovye\is_error;

$op = request::op('default');
$data = [
    'id' => request::int('agent'),
    'month' => request::str('month'),
];

$log = [
    'params' => $data,
];

if ($op == 'repair' && CtrlServ::checkJobSign($data)) {
    $agent = Agent::get($data['id']);
    if (empty($agent)) {
        $log['error'] = '找不到这个代理商！';
        writeLogAndExit();
    }

    $start = microtime(true);

    $result = Agent::repairMonthStats($agent, $log['month']);
    if (is_error($result)) {
        $agent->updateSettings('repair', [
            'error' => $result,
        ]);
    } else {
        $used = microtime(true) - $start;

        $log['used'] = $used;

        $agent->updateSettings('repair', [
            'status' => 'finished',
            'time' => time(),
            'used' => $used,
        ]);
    }

    $agent->save();

    $log['error'] = $result;

} else {
    $log['error'] = '参数或签名错误！';
}


function writeLogAndExit()
{
    global $log;

    Util::logToFile('withdraw', $log);
    Job::exit();
}
