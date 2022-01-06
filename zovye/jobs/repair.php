<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\repair;

use DateTimeImmutable;
use zovye\Agent;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\OrderCounter;
use zovye\request;
use zovye\Stats;
use function zovye\is_error;

$op = request::op('default');
$data = [
    'agent' => request::int('agent'),
    'month' => request::str('month'),
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

    $start = microtime(true);

    $result = Stats::repairMonthData($agent, $data['month']);
    if (is_error($result)) {
        $agent->updateSettings('repair', [
            'error' => $result,
        ]);
        $log['error'] = $result;
    } else {
        $used = microtime(true) - $start;
        $log['used'] = $used;
        $log['save result'] = $agent->save();
    }

    $agent->updateSettings('repair', [
        'status' => 'finished',
        'time' => time(),
        'used' => $used ?? 0,
    ]);

    $counter = new OrderCounter();
    $counter->removeDayAll($agent, new DateTimeImmutable());
    $counter->removeMonthAll($agent, new DateTimeImmutable());
    $counter->removeYearAll($agent, new DateTimeImmutable());

} else {
    $log['error'] = '参数或签名错误！';
}

writeLogAndExit($log);

function writeLogAndExit($log)
{
    Log::debug('repair', $log);
    Job::exit();
}
