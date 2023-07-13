<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\payload_logsModelObj;

$device = Device::get(request('id'));
if (empty($device)) {
    Response::toast('找不到这个设备！', $this->createWebUrl('device'), 'error');
}

$tpl_data['navs'] = [
    'detail' => $device->getName(),
    'payload' => '库存',
    'log' => '事件',
    //'poll_event' => '最新',
    'event' => '消息',
];

$query = $device->payloadQuery();

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$total = $query->count();
if (ceil($total / $page_size) < $page) {
    $page = 1;
}

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id desc');

$logs = [];

/** @var payload_logsModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = [
        'id' => $entry->getId(),
        'org' => $entry->getOrg(),
        'num' => $entry->getNum(),
        'new' => $entry->getOrg() + $entry->getNum(),
        'reason' => strval($entry->getExtraData('reason', '')),
        'code' => strval($entry->getExtraData('code', '')),
        'clr' => strval($entry->getExtraData('clr', '#9e9e9e')),
        'createtime' => $entry->getCreatetime(),
        'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
    ];
    $goods = Goods::get($entry->getGoodsId());
    if ($goods) {
        $data['goods'] = Goods::format($goods, false, true);
    }
    $logs[] = $data;
}

$verified = [];
foreach ($logs as $index => $log) {
    $code = $log['code'];
    if (isset($verified[$code])) {
        continue;
    }
    $create_time = $log['createtime'];
    if (isset($logs[$index + 1])) {
        $verified[$code] = sha1($logs[$index + 1]['code'].$create_time) == $code;
    } else {
        $l = $device->payloadQuery(['id <' => $log['id']])->orderBy('id desc')->findOne();
        if ($l) {
            $verified[$code] = sha1($l->getExtraData('code').$create_time) == $code;
        } else {
            $verified[$code] = sha1(App::uid().$create_time) == $code;
        }
    }
}

$tpl_data['logs'] = $logs;
$tpl_data['verified'] = $verified;
$tpl_data['device'] = $device;
$tpl_data['is_fueling'] = $device->isFuelingDevice();

app()->showTemplate('web/device/payload', $tpl_data);
