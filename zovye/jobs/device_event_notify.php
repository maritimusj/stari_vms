<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\deviceEventNotify;

defined('IN_IA') or exit('Access Denied');

//设备上线通知

use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\Request;

$op = Request::op('default');
$data = [
    'id' => Request::int('id'),
    'event' => Request::str('event'),
];

$log = [
    'data' => $data,
];

if ($op == 'device_event_notify' && CtrlServ::checkJobSign($data)) {
    $device = Device::get($data['id']);
    if ($device) {
        $log['result'] = Device::sendEventTemplateMsg($device, $data['event']);
    } else {
        $log['error'] = '找不到这个设备！';
    }
}

Log::debug('device_event_notify', $log);
