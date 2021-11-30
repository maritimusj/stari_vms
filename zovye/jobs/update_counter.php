<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\update_counter;

use DateTimeImmutable;
use zovye\Agent;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\OrderCounter;
use zovye\request;
use function zovye\app;

$op = request::op('default');
$data = [
    'agent' => request::int('agent'),
    'device' => request::str('device'),
    'datetime' => request::str('datetime'),
];

$log = [
    'params' => $data,

];

if ($op == 'update_counter' && CtrlServ::checkJobSign($data)) {

    $datetime = new DateTimeImmutable($data['datetime']);
    $str = $datetime->format('Y-m-d H:i:s');
    if ($data['agent']) {
        $agent = Agent::get($data['agent']);
        if ($agent) {
            $log["agent $str"] = (new OrderCounter())->getHourAll($agent, $datetime);
        }
    }

    if ($data['device']) {
        $device = Device::get($data['device']);
        if ($device) {
            $log["device $str"] = (new OrderCounter())->getHourAll($device, $datetime);
        }
    }

    if (!isset($agent) && !isset($device)) {
        $log["app $str"] = (new OrderCounter())->getHourAll(app(), $datetime);
    }
} else {
    $log['error'] = '签名检验失败！';
}

Log::debug('update_counter', $log);
