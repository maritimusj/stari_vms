<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\device_logsModelObj;

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

if ($device->isChargingDevice()) {
    unset($tpl_data['navs']['payload']);
    unset($tpl_data['navs']['log']);
}

$query = $device->logQuery();
if (Request::isset('way')) {
    $query->where(['level' => Request::int('way')]);
}

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$total = $query->count();
if (ceil($total / $page_size) < $page) {
    $page = 1;
}

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$query->page($page, $page_size);
$query->orderBy('id DESC');

$logs = [];

/** @var device_logsModelObj $entry */
foreach ($query->findAll() as $entry) {
    $data = [
        'id' => $entry->getId(),
        'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        'imei' => $entry->getTitle(),
        'title' => Device::formatPullTitle($entry->getLevel()),
        'goods' => $entry->getData('goods'),
        'user' => $entry->getData('user'),
    ];

    $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

    $result = $entry->getData('result');
    if (is_array($result)) {
        $result_data = $result['data'] ?? $result;
        if (isset($result_data['errno'])) {
            $data['result'] = [
                'errno' => intval($result_data['errno']),
                'message' => $result_data['message'],
            ];
        } else {
            $data['result'] = [
                'errno' => -1,
                'message' => $result_data['message'] ?: '<未知>',
            ];
        }
        $data['result']['orderUID'] = strval($result_data['orderUID']);
        $data['result']['serialNO'] = strval($result_data['serialNO']);
        $data['result']['timeUsed'] = intval($result_data['timeUsed']);
    } else {
        $data['result'] = [
            'errno' => empty($result),
            'message' => empty($result) ? '失败' : '成功',
        ];
    }

    $confirm = $entry->getData('confirm', []);
    if ($confirm) {
        $data['confirm'] = [
            'errno' => $confirm['result'],
            'message' => Order::desc($confirm['result']),
        ];
    }

    $order_tid = $entry->getData('order.tid');
    if ($order_tid) {
        $data['memo'] = $order_tid;
    }

    $acc = $entry->getData('account');
    if ($acc) {
        $data['memo'] = $acc['title'].'('.$acc['name'].')';
    }

    $order_id = $entry->getData('order');
    if ($order_id) {
        $order = Order::get($order_id);
        if ($order) {
            $data['order'] = [
                'uid' => $order->getOrderNO(),
            ];
        }
    }

    $logs[] = $data;
}

$tpl_data['logs'] = $logs;
$tpl_data['device'] = $device;

Response::showTemplate('web/device/log', $tpl_data);