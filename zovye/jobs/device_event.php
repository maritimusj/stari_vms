<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\DeviceOnline;

defined('IN_IA') or exit('Access Denied');

//设备上线通知

use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\Request;

$op = Request::op('default');
$data = [
    'id' => request::int('id'),
    'event' => request::str('event'),
];

$log = [
    'data' => $data,
];

if ($op == 'device_event' && CtrlServ::checkJobSign($data)) {
    $device = Device::get($data['id']);
    if ($device) {
        $log['send template msg'] = Device::sendEventTemplateMsg($device, $data['event']);
    }
}

Log::debug('device_event', $log);
