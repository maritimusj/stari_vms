<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\update_counter;

defined('IN_IA') or exit('Access Denied');

use DateTimeImmutable;
use Exception;
use zovye\CtrlServ;
use zovye\domain\Agent;
use zovye\domain\Device;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use zovye\util\OrderCounter;
use function zovye\app;

$log = [
    'agent' => Request::int('agent'),
    'device' => Request::str('device'),
    'datetime' => Request::str('datetime'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!');
}

$counter = new OrderCounter();
try {
    $datetime = new DateTimeImmutable($log['datetime']);
    $str = $datetime->format('Y-m-d H:i:s');
    if ($log['agent']) {
        $agent = Agent::get($log['agent']);
        if ($agent) {
            $log["agent $str"] = $counter->getHourAll([$agent, 'goods'], $datetime);
        }
    }
    if ($log['device']) {
        $device = Device::get($log['device']);
        if ($device) {
            $log["device $str"] = $counter->getHourAll([$device, 'goods'], $datetime);
        }
    }
    if (!isset($agent) && !isset($device)) {
        $log["app $str"] = $counter->getHourAll([app(), 'goods'], $datetime);
    }
} catch (Exception $e) {
    $log['error'] = $e->getMessage();
}

Log::debug('update_counter', $log);
