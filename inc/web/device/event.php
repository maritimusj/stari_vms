<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Device;
use zovye\model\device_eventsModelObj;
use zovye\util\Util;

$device = Device::get(Request::int('id'));
if (empty($device)) {
    Response::toast('找不到这个设备！', Util::url('device'), 'error');
}

$query = $device->eventQuery();

if (Request::isset('enable')) {
    $enable = Request::bool('enable');
    $device->setEventLogEnabled($enable);
    $device->save();
}

$tpl_data['enabled'] = $device->isEventLogEnabled();

if (Request::isset('event')) {
    $query->where(['event' => Request::trim('event')]);
}

$detail = Request::bool('detail');

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$total = $query->count();
if (ceil($total / $page_size) < $page) {
    $page = 1;
}

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id DESC');

$events = [];
/** @var device_eventsModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = [
        'id' => $entry->getId(),
        'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        'event' => $entry->getEvent(),
    ];
    if ($device->isBlueToothDevice()) {
        $data['title'] = $entry->getExtraData('message');
    } else {
        $data['title'] = DeviceEventProcessor::logEventTitle($entry->getEvent());
    }
    if ($detail) {
        $data['extra'] = $entry->getExtra();
    }
    $events[] = $data;
}

$tpl_data['navs'] = [
    'detail' => $device->getName(),
    'payload' => '库存',
    'log' => '事件',
    //'poll_event' => '最新',
    'event' => '消息',
];

if ($device->isChargingDevice()) {
    unset($tpl_data['navs']['payload']);
    unset($tpl_data['navs']['log']);
}

$tpl_data['events'] = $events;
$tpl_data['device'] = $device;

Response::showTemplate('web/device/event', $tpl_data);
