<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Device;
use zovye\domain\User;
use zovye\domain\UserLogs;
use zovye\model\user_logsModelObj;

$query = UserLogs::query();

if (Request::has('orderNO')) {
    $query->where(['title' => Request::str('orderNO')]);
    $total = 1;
    $page = 1;
    $page_size = 1;
} else {
    $query->where([
        'level' => [
            LOG_GOODS_PAY,
            LOG_CHARGING_PAY,
            LOG_FUELING_PAY,
            LOG_RECHARGE,
        ],
    ])->orderBy('id DESC');

    //使用自增长id做为总数，数据量大时使用$query->count()效率太低，
    //注意：需要先调用$query->orderBy('id DESC');
    $last = $query->findOne();
    $total = $last ? $last->getId() : 0;

    $page = max(1, Request::str('page'));
    $page_size = Request::is_ajax() ? 10 : max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

    if (ceil($total / $page_size) < $page) {
        $page = 1;
    }
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

    if (empty($log['data']['payResult']) && empty($log['data']['queryResult'])) {
        $log['data']['queryResult'] = Pay::query($log['orderNO']);
    }

    $log['transaction_id'] = $log['data']['payResult']['transaction_id']
        ?? $log['data']['queryResult']['transaction_id'];

    $log['refund'] = $log['transaction_id'] && empty($log['data']['refund']);

    $log['refund_total'] = $log['data']['refund']['total']
        ?? $log['data']['refund']['result']['data']['total_amount']
        ?? $log['data']['refund']['result']['refund_fee'];

    $logs[] = $log;
}

$tpl_data['logs'] = $logs;
$tpl_data['way'] = 'pay';

Response::showTemplate('web/order/log', $tpl_data);