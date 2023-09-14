<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if (empty($device)) {
    Response::toast('找不到这个设备！', Util::url('device'), 'error');
}

$device->eventQuery()->delete();

Response::toast(
    '已清除所有消息日志！',
    Util::url('device', ['op' => 'event', 'id' => $device->getId()]),
    'success'
);