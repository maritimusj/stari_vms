<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateInterval;
use DateTime;
use Exception;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$order = Order::get($id);
if (!$order) {
    JSON::fail('找不到这个订单！');
}

function formatTimeTotalF($ts): string
{
    try {
        $interval = new DateInterval('PT'.intval($ts).'S');
        $time = new DateTime('00:00:00');
        $time->add($interval);
        return $time->format('H:i:s');
    } catch (Exception $e) {
    }
    return '';
}

if ($order->isFuelingOrder()) {
    $list = [];

    $data = $order->getFuelingRecord();

    $list[] = $data;
    $data['time_total_formatted'] = formatTimeTotalF($data);
    foreach ($data as $i => $v) {
        if (is_array($v) && $v['ser']) {
            $v['time_total_formatted'] = formatTimeTotalF($v['time_total']);
            $list[] = $v;
            unset($data[$i]);
        }
    }

    $content = app()->fetchTemplate(
        'web/fueling/detail',
        [
            'list' => $list,
        ]
    );
} elseif ($order->isChargingOrder()) {
    $content = app()->fetchTemplate(
        'web/charging/detail',
        [
            'data' => $order->getChargingRecord(),
        ]
    );
} else {
    $content = '没有计费信息!';
}

JSON::success(['title' => '计费详情', 'content' => $content]);