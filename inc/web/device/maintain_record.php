<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\model\device_recordModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

$is_export = request('is_export');

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$device_id = Request::int('device_id');

$date_limit = Request::array('datelimit');
if ($date_limit['start']) {
    try {
        $s_date = new DateTime($date_limit['start'].'00:00');
    } catch (Exception $e) {
    }
} else {
    $s_date = new DateTime('first day of this month 00:00');
}

if ($date_limit['end']) {
    try {
        $e_date = new DateTime($date_limit['end'].' 00:00');
    } catch (Exception $e) {
    }
} else {
    $e_date = new DateTime('first day of next month 00:00');
}

$agent_openid = Request::str('agent_openid');
$nickname = Request::trim('nickname');

$user_ids = [];
if ($nickname != '') {
    $user_res = User::query()->whereOr([
        'nickname LIKE' => "%$nickname%",
        'mobile LIKE' => "%$nickname%",
    ])->findAll();
    foreach ($user_res as $item) {
        $user_ids[] = $item->getId();
    }
}

$device_ids = [];
if (!empty($agent_openid)) {
    $agent = Agent::get($agent_openid, true);
    if ($agent) {
        $device_res = Device::query(['agent_id' => $agent->getId()])->findAll();
        foreach ($device_res as $item) {
            $device_ids[] = $item->getId();
        }
    }
}

$condition = [
    'createtime >=' => $s_date->getTimestamp(),
    'createtime <' => $e_date->getTimestamp(),
];

if (!empty($device_id)) {
    $condition['device_id'] = $device_id;
}

$cate = Request::int('cate');
if (!empty($cate)) {
    $condition['cate'] = $cate;
}

$query = m('device_record')->query($condition);
if ($nickname != '') {
    if (empty($user_ids)) {
        $query->where('id = -1');
    } else {
        $user_ids = array_unique($user_ids);
        $query->where(['user_id' => $user_ids]);
    }
}

if (!empty($agent_openid)) {
    if (empty($device_ids)) {
        $query->where('id = -1');
    } else {
        $device_ids = array_unique($device_ids);
        $query->where(['device_id' => $device_ids]);
    }
}

if ($is_export) {
    $query->orderBy('id DESC');
    $res = $query->findAll();
} else {
    $total = $query->count();

    $query->orderBy('id DESC');
    $res = $query->page($page, $page_size)->findAll();
}

$data = [];
$user_ids = [];
$device_ids = [];

/** @var  device_recordModelObj $item */
foreach ($res as $item) {
    $arr = [
        'id' => $item->getId(),
        'deviceId' => $item->getDeviceId(),
        'userId' => $item->getUserId(),
        'cate' => $item->getCate(),
        'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
    ];

    $data[] = $arr;
    $user_ids[] = $item->getUserId();
    $device_ids[] = $item->getDeviceId();
}

$user_assoc = [];
if (!empty($user_ids)) {
    $user_ids = array_unique($user_ids);
    $user_res = User::query()->where(['id' => $user_ids])->findAll();
    /** @var userModelObj $item */
    foreach ($user_res as $item) {
        $user_assoc[$item->getId()] = $item->getNickname();
    }
}

$device_assoc = [];
$device_agent_assoc = [];
$agent_ids = [];
if (!empty($device_ids)) {
    $device_ids = array_unique($device_ids);
    $device_res = Device::query()->where(['id' => $device_ids])->findAll();
    /** @var deviceModelObj $item */
    foreach ($device_res as $item) {
        $device_assoc[$item->getId()] = $item->getName().', '.$item->getImei();
        $device_agent_assoc[$item->getId()] = $item->getAgentId();
        $agent_ids[] = $item->getAgentId();
    }
}

$agent_assoc = [];
$agent_assoc[0] = '平台';
if (!empty($agent_ids)) {
    $agent_ids = array_unique($agent_ids);
    $agent_res = m('agent_vw')->where('id IN('.implode(',', $agent_ids).')')->findAll();
    foreach ($agent_res as $item) {
        $agent_assoc[$item->getId()] = $item->getNickname();
    }
}

$rec_type = [
    '1' => '开门记录',
    '2' => '消毒记录',
    '3' => '换电池记录',
];

if ($is_export) {
    $title = [
        '设备名称',
        '代理商',
        '操作人员',
        '类型',
        '日期',
    ];
    $e_data = [];
    foreach ($data as $item) {
        $e_data[] = [
            $device_assoc[$item['deviceId']],
            $agent_assoc[$device_agent_assoc[$item['deviceId']]],
            $user_assoc[$item['userId']],
            $rec_type[$item['cate']],
            $item['createtime'],
        ];
    }

    Util::exportCSV('维护记录', $title, $e_data);
    exit();
} else {

    $tpl_data['s_date'] = $s_date;
    $tpl_data['e_date'] = $e_date;
    $tpl_data['nickname'] = $nickname;
    $tpl_data['device_id'] = $device_id;
    $tpl_data['open_id'] = $agent_openid;
    $tpl_data['cate'] = $cate;

    $tpl_data['data'] = $data;
    $tpl_data['user_assoc'] = $user_assoc;
    $tpl_data['device_assoc'] = $device_assoc;
    $tpl_data['device_agent_assoc'] = $device_agent_assoc;
    $tpl_data['agent_assoc'] = $agent_assoc;

    $tpl_data['rec_type'] = $rec_type;

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    Response::showTemplate('web/device/record', $tpl_data);
}