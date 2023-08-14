<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\ranking;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\Agent;
use zovye\App;
use zovye\CacheUtil;
use zovye\Device;
use zovye\JSON;
use zovye\model\orderModelObj;
use zovye\Order;
use zovye\Request;
use zovye\Response;
use zovye\Util;

$fn = Request::str('fn', 'default');

if ($fn == 'default') {

    $begin = new DateTime('first day of this month');
    $end = new DateTime();

    $tpl_data = [
        'years' => getYears(Order::getFirstOrder()),
        's_start_date' => $begin->format('Y-m-d'),
        's_end_date' => $end->format('Y-m-d'),
        'api_url' => Util::url('homepage', ['op' => 'ranking']),
    ];

    Response::showTemplate('web/home/ranking', $tpl_data);

} elseif ($fn == 'agent') {

    extract(getParsedDate());

    $sort = Request::trim('sort', 'price');
    if (!in_array($sort, ['total', 'price', 'amount'])) {
        $sort = 'price';
    }

    $result = CacheUtil::cachedCall(30, function () use ($begin, $end, $sort, $title) {
        $query = Order::query();

        if ($begin) {
            $query->where(['createtime >=' => $begin->getTimestamp()]);
        }

        if ($end) {
            $query->where(['createtime <' => $end->getTimestamp()]);
        }

        $query->groupBy('agent_id');
        $query->orderBy("$sort DESC");

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

        $summary['price'] = number_format($summary['price'] / 100, 2, '.', '');
        if (App::isFuelingDeviceEnabled()) {
            $summary['amount'] = number_format($summary['amount'] / 100, 2, '.', '');
        }

        return [
            'begin' => isset($begin) ? $begin->format('Y-m-d') : '',
            'end' => isset($end) ? $end->modify('-1 day')->format('Y-m-d') : '',
            'sort' => $sort,
            'list' => $list,
            'title' => $title,
            'summary' => $summary,
        ];
    }, $begin ? $begin->getTimestamp() : 0, $end ? $end->getTimestamp() : 0, $sort);

    JSON::success($result);

} elseif ($fn == 'device') {
    extract(getParsedDate());


    $agent_id = Request::int('id');
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            JSON::fail('找不到这个代理商！');
        }
    }

    $sort = Request::trim('sort', 'price');
    if (!in_array($sort, ['total', 'price', 'amount'])) {
        $sort = 'price';
    }

    $result = CacheUtil::cachedCall(30, function () use ($begin, $end, $sort, $agent, $title) {
        $query = Order::query();

        if ($agent) {
            $query->where(['agent_id' => $agent->getId()]);
        }

        if ($begin) {
            $query->where(['createtime >=' => $begin->getTimestamp()]);
        }

        if ($end) {
            $query->where(['createtime <' => $end->getTimestamp()]);
        }

        $query->groupBy('device_id');
        $query->orderBy("$sort DESC");

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
                    'amount' => App::isFuelingDeviceEnabled() ?
                        number_format($item['amount'] / 100, 2, '.', '') : $item['amount'],
                ];
                $summary['order'] += intval($item['total']);
                $summary['price'] += $item['price'];
                $summary['amount'] += $item['amount'];
            }
        }

        $summary['price'] = number_format($summary['price'] / 100, 2, '.', '');
        if (App::isFuelingDeviceEnabled()) {
            $summary['amount'] = number_format($summary['amount'] / 100, 2, '.', '');
        }

        return [
            'begin' => isset($begin) ? $begin->format('Y-m-d') : '',
            'end' => isset($end) ? $end->modify('-1 day')->format('Y-m-d') : '',
            'sort' => $sort,
            'list' => $list,
            'title' => $title,
            'summary' => $summary,
            'years' => getYears($agent ? Order::getFirstOrderOfAgent($agent) : Order::getFirstOrder()),
        ];
    }, $begin ? $begin->getTimestamp() : 0, $end ? $end->getTimestamp() : 0, $sort, $agent ? $agent->getId() : 0);

    JSON::success($result);
}

function getYears($first_order): array
{
    $years = [];

    if (is_array($first_order) && $first_order) {
        $begin = new DateTime();

        $begin->setTimestamp($first_order['createtime']);
        $begin->modify('first day of jan 00:00 this year');

        $now = new DateTime();
        while ($begin < $now) {
            $years[] = $begin->format('Y');
            $begin->modify('next year');
        }
    }

    return $years;
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
