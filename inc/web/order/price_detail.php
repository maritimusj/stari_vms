<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateInterval;
use DateTime;
use Exception;
use zovye\domain\Order;

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
    if (!$order->isFuelingFinished()) {
        JSON::fail('订单还没有结束，无法查看！');
    }

    $list = [];

    $data = $order->getFuelingRecord();

    if ($data) {
        $data['time_total_formatted'] = formatTimeTotalF($data['time_total']);
        $list[] = $data;

        foreach ($data as $i => $v) {
            if (is_array($v) && $v['ser']) {
                $v['time_total_formatted'] = formatTimeTotalF($v['time_total']);
                $list[] = $v;
                unset($data[$i]);
            }
        }
    }

    Response::templateJSON(
        'web/fueling/detail','计费详情',
        [
            'list' => $list,
        ]
    );
} elseif ($order->isChargingOrder()) {
    Response::templateJSON(
        'web/charging/detail','计费详情',
        [
            'data' => $order->getChargingRecord(),
        ]
    );
}

JSON::fail('没有计费信息!');