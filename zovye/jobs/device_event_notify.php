<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\deviceEventNotify;

defined('IN_IA') or exit('Access Denied');

//设备上线通知

use zovye\CtrlServ;
use zovye\domain\Device;
use zovye\JobException;
use zovye\Log;
use zovye\Request;

$log = [
    'id' => Request::int('id'),
    'event' => Request::str('event'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$device = Device::get($log['id']);
if ($device) {
    $log['result'] = Device::sendEventTemplateMsg($device, $log['event']);
} else {
    $log['error'] = '找不到这个设备！';
}

Log::debug('device_event_notify', $log);
