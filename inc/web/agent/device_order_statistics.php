<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTimeImmutable;
use Exception;
use zovye\domain\Agent;
use zovye\domain\Device;
use zovye\model\deviceModelObj;

$agent_id = Request::int('id');

$agent = Agent::get($agent_id);

if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$month = '';
if (Request::has('month')) {
    $month_str = Request::str('month');
    try {
        $month = new DateTimeImmutable($month_str);
    } catch (Exception $e) {
        JSON::fail('时间格式不正确！');
    }
    $fn = function ($device) use ($month) {
        return Statistics::deviceOrderMonth($device, $month);
    };
} else {
    $start = Request::str('start');
    $end = Request::str('end');
    $fn = function ($device) use ($start, $end) {
        return Statistics::deviceOrder($device, $start, $end);
    };
}

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Device::query(['agent_id' => $agent->getId()]);

$total = $query->count();
$total_page = ceil($total / $page_size);

$query->page($page, $page_size);

$result = [
    'page' => $page,
    'totalpage' => $total_page,
    'list' => [],
];
/** @var deviceModelObj $device */
foreach ($query->findAll() as $device) {
    $result['list'][] = [
        'id' => $device->getId(),
        'uid' => $device->getUID(),
        'name' => $device->getName(),
        'stats' => $fn($device),
    ];
}

JSON::success($result);
