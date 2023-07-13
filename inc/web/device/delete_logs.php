<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(request('id'));
if (empty($device)) {
    Response::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
}

$device->eventQuery()->delete();

Response::itoast(
    '已清除所有消息日志！',
    $this->createWebUrl('device', ['op' => 'event', 'id' => $device->getId()]),
    'success'
);