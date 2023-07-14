<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use DateTime;
use Exception;

defined('IN_IA') or exit('Access Denied');

$fn = Request::str('fn', 'default');

if ($fn == 'default') {

    $years = [];
    $first_order = Order::getFirstOrder();
    if ($first_order) {
        $begin = new DateTime();

        $begin->setTimestamp($first_order['createtime']);
        $begin->modify('first day of jan 00:00 this year');

        $now = new DateTime();
        while ($begin < $now) {
            $years[] = $begin->format('Y');
            $begin->modify('next year');
        }
    }

    $begin = new DateTime('first day of this month');
    $end = new DateTime();

    $tpl_data = [
        'years' => $years,
        's_start_date' => $begin->format('Y-m-d'),
        's_end_date' => $end->format('Y-m-d'),
        'api_url' => Util::url('fueling', ['op' => 'stats']),
    ];

    Response::showTemplate('web/home/ranking', $tpl_data);

} elseif ($fn == 'agent') {

    extract(getParsedDate());

    $query = Order::query();

    if ($begin) {
        $query->where(['createtime >=' => $begin->getTimestamp()]);
    }
   
    if ($end) {
        $query->where(['createtime <' => $end->getTimestamp()]);
    }
    
    $query->groupBy('agent_id');
    $query->orderBy('price DESC');

    $list = [];
    $summary = [
        'order' => 0,
        'price' => 0,
        'amount' => 0,
    ];

    $all = $query->getAll(['agent_id', 'COUNT(*) AS total', 'SUM(price) AS price', 'SUM(num) AS amount']);
    foreach ((array)$all as $item) {
        $agent = Agent::get($item['agent_id']);
        if ($agent) {
            $device_total = Device::query(['agent_id' => $agent->getId()])->count();
            $list[] = [
                'agent' => $agent->profile(false),
                'devices_total' => $device_total,
                'order_total' => intval($item['total']),
                'price' => number_format($item['price'] / 100, 2, '.', ''),
                'amount' => App::isFuelingDeviceEnabled() ?
                    number_format($item['amount'] / 100, 2, '.', '') : $item['amount'],
            ];
            $summary['order'] += intval($item['total']);
            $summary['price'] += $item['price'];
            $summary['amount'] += $item['amount'];
        }
    }

    $summary['price'] = number_format($summary['price'], 2, '.', '');
    if (App::isFuelingDeviceEnabled()) {
        $summary['amount'] = number_format($summary['amount'], 2, '.', '');
    }


    JSON::success([
        'begin' => isset($begin) ? $begin->format('Y-m-d') : '',
        'end' => isset($end) ? $end->modify('-1 day')->format('Y-m-d') : '',
        'list' => $list,
        'title' => $title,
        'summary' => $summary,
    ]);

} elseif ($fn == 'device') {
    extract(getParsedDate());

    $agent = Agent::get(Request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $query = Order::query(['agent_id' => $agent->getId()]);

    if ($begin) {
        $query->where(['createtime >=' => $begin->getTimestamp()]);
    }
   
    if ($end) {
        $query->where(['createtime <' => $end->getTimestamp()]);
    }

    $query->groupBy('device_id');
    $query->orderBy('price DESC');

    $list = [];
    $summary = [
        'order' => 0,
        'price' => 0,
        'amount' => 0,
    ];
    $all = $query->getAll(['device_id', 'COUNT(*) AS total', 'SUM(price) AS price', 'SUM(num) AS amount']);
    foreach ((array)$all as $item) {
        $device = Device::get($item['device_id']);
        if ($device) {
            $list[] = [
                'device' => $device->profile(),
                'order_total' => intval($item['total']),
                'price' => number_format($item['price'] / 100, 2, '.', ''),
                'amount' => App::isFuelingDeviceEnabled()?
                    number_format($item['amount'] / 100, 2, '.', '') : $item['amount'],
            ];
            $summary['order'] += intval($item['total']);
            $summary['price'] += $item['price'];
            $summary['amount'] += $item['amount'];
        }
    }

    $summary['price'] = number_format($summary['price'], 2, '.', '');
    if (App::isFuelingDeviceEnabled()) {
        $summary['amount'] = number_format($summary['amount'], 2, '.', '');
    }

    JSON::success([
        'begin' => isset($begin) ? $begin->format('Y-m-d') : '',
        'end' => isset($end) ? $end->modify('-1 day')->format('Y-m-d') : '',
        'list' => $list,
        'title' => $title,
        'summary' => $summary,
    ]);
}

function getParsedDate(): array
{
    $begin = null;
    $end = null;
    try {
        $res = explode('-', Request::str('begin'), 3);
        if (count($res) == 1 && !empty($res[0])) {
            $begin = new DateTime(sprintf("%d-01-01 00:00", $res[0]));
        } elseif (count($res) == 2) {
            $begin = new DateTime(sprintf("%d-%02d-01", $res[0], $res[1]));
        } elseif (count($res) == 3) {
            $begin = new DateTime(sprintf("%d-%02d-%02d", $res[0], $res[1], $res[2]));
        }
    } catch (Exception $e) {
    }

    try {
        $res = explode('-', Request::str('end'), 3);
        if (count($res) == 1 && !empty($res[0])) {
            $end = new DateTime(sprintf("%d-01-01 00:00", $res[0]));
            $end->modify("first day of jan next year");
        } elseif (count($res) == 2) {
            $end = new DateTime(sprintf("%d-%02d-01", $res[0], $res[1]));
            $end->modify('first day of next month');
        } elseif (count($res) == 3) {
            $end = new DateTime(sprintf("%d-%02d-%02d", $res[0], $res[1], $res[2]));
            $end->modify('next day');
        }
    } catch (Exception $e) {
    }

    return ['begin' => $begin, 'end' => $end];
}
