<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_eventsModelObj;

$device = Device::get(request('id'));
if (empty($device)) {
    Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
}

$query = $device->eventQuery();

$the_first_id = request('the_first_id') ?: 0;

$query->where(['event' => [14, 20]]);
$query->where(['id >' => $the_first_id]);

$res = $query->findAll();
if (count($res) == 0) {
    echo json_encode([]);
} else {
    $events = [];
    /** @var device_eventsModelObj $item */
    foreach ($res as $item) {
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
    echo json_encode($events);
}
