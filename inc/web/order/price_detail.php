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

if ($order->isFuelingOrder()) {
    $data = $order->getFuelingRecord();
    if ($data['time_total'] > 0) {
        try {
            $interval = new DateInterval('PT'.$data['time_total'].'S');
            $time = new DateTime('00:00:00');
            $time->add($interval);

            if ($data['time_total'] < 60) {
                $format_str = 's';
            } elseif ($data['time_total'] < 60 * 60) {
                $format_str = 'i:s';
            } else {
                $format_str = 'H:i:s';
            }
            $data['time_total_formatted'] = $time->format($format_str);
        } catch (Exception $e) {
        }
    }

    $content = app()->fetchTemplate(
        'web/fueling/detail',
        [
            'data' => $data,
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