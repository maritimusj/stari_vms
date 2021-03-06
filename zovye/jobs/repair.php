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
use zovye\request;

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

    $counter = new OrderCounter();
    try {
        $begin = new DateTime($data['month']);
        $end = new DateTime($begin->format('Y-m-d H:i:s'));
        $end->modify('first day of next month 00:00');

        $counter->removeMonthAll([$agent, 'goods'], $begin);

        while ($begin < $end) {
            $counter->removeDayAll([$agent, 'goods'], $begin);
            $begin->modify('+ 1 days');
        }
    } catch (Exception $e) {
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
