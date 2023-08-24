<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_eventsModelObj;

$device = Device::get(Request::int('id'));
if (empty($device)) {
    Response::toast('找不到这个设备！', Util::url('device'), 'error');
}

$query = $device->eventQuery();

$query->where(['event' => [14, 20]]);
$query->orderBy('id DESC');
$query->limit(10);

$events = [];
$the_first_id = 0;

/** @var device_eventsModelObj $item */
foreach ($query->findAll() as $item) {
    if (!$the_first_id) {
        $the_first_id = $item->getId();
    }

    $extra = json_decode($item->getExtra(), true);
    $extra = $extra['extra'];

    $arr = [];
    $arr['id'] = $item->getId();
    $arr['time'] = date('H:i:s', $item->getCreatetime());
    if ($item->getEvent() == 14) {
        $rssi = $extra['RSSI'] ?: 0;
        $per = floor($rssi * 100 / 31);
        $iccid = $extra['ICCID'] ?: '';

        $arr['type'] = 14;
        $arr['per'] = $per;
        $arr['iccid'] = $iccid;
    }

    if ($item->getEvent() == 20) {
        $sw = $extra['sw'] ?: [];
        $f_sw = [];
        foreach ($sw as $val) {
            if ($val == 1) {
                $f_sw[] = '工作';
            } else {
                $f_sw[] = '待机';
            }
        }

        $door = $extra['door'] ?: [];
        $f_door = [];
        foreach ($door as $val) {
            if ($val == 1) {
                $f_door[] = '关';
            } else {
                $f_door[] = '开';
            }
        }

        $arr['type'] = 20;
        $arr['sw'] = $f_sw;
        $arr['door'] = $f_door;
        $arr['temperature'] = $extra['temperature'];
        $arr['weights'] = $extra['weights'];
    }

    $events[] = $arr;
}

$tpl_data['navs'] = [
    'detail' => $device->getName(),
    'log' => '日志',
    //'poll_event' => '最新',
    'event' => '事件',
];

$tpl_data['device'] = $device;
$tpl_data['events'] = $events;
$tpl_data['the_first_id'] = $the_first_id;

Response::showTemplate('web/device/poll_event', $tpl_data);
