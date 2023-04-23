<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use DateTime;
use DateTimeImmutable;
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
            $years[] = $begin->format('Y年');
            $begin->modify('next year');
        }
    }

    $tpl_data = [
        'years' => $years,
        'api_url' => Util::url('fueling', ['op' => 'stats']),
    ];

    app()->showTemplate('web/fueling/stats', $tpl_data);

} elseif ($fn == 'agent') {

    extract(getParsedDate());

    $query = Order::query();
    $query->where(['createtime <=' => $begin->getTimestamp()]);
    $query->where(['createtime >' => $end->getTimestamp()]);
    $query->groupBy('agent_id');
    $query->orderBy('price DESC');

    $list = [];
    foreach ($query->getAll(['agent_id', 'SUM(price) AS price', 'SUM(num) AS total']) as $item) {
        $agent = Agent::get($item['agent_id']);
        if ($agent) {
            $list[] = [
                'agent' => $agent->profile(false),
                'price' => number_format($item['price'] / 100, 2, '.', ''),
                'total' => number_format($item['total'] / 100, 2, '.', ''),
            ];
        }
    }

    JSON::success([
        'list' => $list,
        'title' => $title,
    ]);

} elseif ($fn == 'device') {
    extract(getParsedDate());

    $agent = Agent::get(Request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $query = Order::query(['agent_id' => $agent->getId()]);

    $query->where(['createtime <=' => $begin->getTimestamp()]);
    $query->where(['createtime >' => $end->getTimestamp()]);
    $query->groupBy('device_id');
    $query->orderBy('price DESC');

    $list = [];
    foreach ($query->getAll(['device_id', 'SUM(price) AS price', 'SUM(num) AS total']) as $item) {
        $device = Device::get($item['device_id']);
        if ($device) {
            $list[] = [
                'device' => $device->profile(),
                'price' => number_format($item['price'] / 100, 2, '.', ''),
                'total' => number_format($item['total'] / 100, 2, '.', ''),
            ];
        }
    }

    JSON::success([
        'list' => $list,
        'title' => $title,
    ]);
}

function getParsedDate(): array
{
    $title = '';
    try {
        $res = explode('-', Request::str('date'), 3);
        if (count($res) == 1) {
            $begin = new DateTimeImmutable(sprintf("%d-01-01 00:00", $res[0]));
            $end = $begin->modify("first day of jan next year");
            $title = $begin->format('Y年');
        } elseif (count($res) == 2) {
            $begin = new DateTimeImmutable(sprintf("%d-%02d-01", $res[0], $res[1]));
            $end = $begin->modify('first day of next month');
            $title = $begin->format('Y年m月');
        } else {
            $begin = new DateTimeImmutable(sprintf("%d-%02d-%02d", $res[0], $res[1], $res[2]));
            $end = $begin->modify('next day');
            $title = $begin->format('Y年m月d日');
        }
    } catch (Exception $e) {
    }

    if (!isset($begin)) {
        $begin = new DateTimeImmutable('today 00:00');
    }

    if (!isset($end)) {
        $end = new DateTimeImmutable('tomorrow 00:00');
    }

    return ['begin' => $begin, 'end' => $end, 'title' => $title];
}
