<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\user_logsModelObj;

$page = max(1, Request::str('page'));
$page_size = Request::is_ajax() ? 10 : max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

$query = m('user_logs')->where([
    'level' => [
        LOG_GOODS_PAY,
        LOG_CHARGING_PAY,
        LOG_RECHARGE,
    ],
])->orderBy('id DESC');

//使用自增长id做为总数，数据量大时使用$query->count()效率太低，
//注意：需要先调用$query->orderBy('id desc');
$last = $query->findOne();
$total = $last ? $last->getId() : 0;

if (ceil($total / $page_size) < $page) {
    $page = 1;
}

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$logs = [];

/** @var user_logsModelObj $entry */
foreach ($query->page($page, $page_size)->findAll() as $entry) {
    $log = [
        'id' => $entry->getId(),
        'level' => $entry->getLevel(),
        'orderNO' => $entry->getTitle(),
        'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
    ];

    $log['data'] = $entry->getData();
    $user = User::get($log['data']['user'], true);
    if ($user) {
        $log['user'] = $user->profile();
    }

    $device = Device::get($log['data']['device']);
    if ($device) {
        $log['device'] = [
            'name' => $device->getName(),
            'id' => $device->getId(),
        ];
    }

    if (empty($log['data']['payResult'])) {
        $log['data']['queryResult'] = Pay::query($log['orderNO']);
    }

    $logs[] = $log;
}

$tpl_data['logs'] = $logs;
$tpl_data['way'] = 'pay';

app()->showTemplate('web/order/log', $tpl_data);