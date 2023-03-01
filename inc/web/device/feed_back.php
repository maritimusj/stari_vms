<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\device_feedbackModelObj;

$date_limit = Request::array('datelimit');
if ($date_limit['start']) {
    $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'].' 00:00:00');
} else {
    $s_date = new DateTime('first day of this month 00:00:00');
}

if ($date_limit['end']) {
    $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'].' 00:00:00');
    $e_date->modify('next day');
} else {
    $e_date = new DateTime('first day of next month 00:00:00');
}

$condition = [
    'createtime >=' => $s_date->getTimestamp(),
    'createtime <' => $e_date->getTimestamp(),
];

$device_id = Request::int('device_id');

if (!empty($device_id)) {
    $condition['device_id'] = $device_id;
}

$query = m('device_feedback')->query($condition);

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$total = $query->count();

$query->orderBy('id DESC');
$query->page($page, $page_size);

$data = [];
/** @var device_feedbackModelObj $item */
foreach ($query->findAll() as $item) {
    $pics = unserialize($item->getPics());
    if ($pics === false) {
        $pics = [];
    } else {
        foreach ($pics as $index => $pic) {
            $pics[$index] = Util::toMedia($pic);
        }
    }

    $arr = [
        'id' => $item->getId(),
        'text' => $item->getText(),
        'pics' => $pics,
        'remark' => $item->getRemark(),
        'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
    ];

    $user = User::get($item->getUserId());
    if ($user) {
        $arr['user'] = $user->profile();
    }

    $device = Device::get($item->getDeviceId());
    if ($device) {
        $arr['device'] = [
            'imei' => $device->getImei(),
            'name' => $device->getName(),
        ];
        $agent = $device->getAgent();
        if ($agent) {
            $arr['agent'] = $agent->profile();
        }
    }

    $data[] = $arr;
}

$tpl_data['s_date'] = $s_date->format('Y-m-d');
$tpl_data['e_date'] = $e_date->modify('-1 day')->format('Y-m-d');
$tpl_data['device_id'] = $device_id;
$tpl_data['data'] = $data;
$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

app()->showTemplate('web/device/feedback', $tpl_data);