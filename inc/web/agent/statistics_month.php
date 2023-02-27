<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;
use DateTimeImmutable;
use Exception;

$agent_id = request::int('id');
$agent = Agent::get($agent_id);

if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$year_str = request::int('year');
$month_str = request::int('month');
$day_str = request::int('day');

try {
    $month = new DateTimeImmutable(sprintf("%d-%02d-%02d", $year_str, $month_str, $day_str));
    if ($month->format('m') != $month_str) {
        JSON::fail('时间格式不正确！');
    }
} catch (Exception $e) {
    JSON::fail('时间格式不正确！');
}

$result = [
    'title' => $month->format('Y年m月'),
    'list' => [],
    'summary' => [],
];

$first_order = Order::getFirstOrderOf($agent);
if ($first_order) {
    try {
        $order_date_obj = new DateTime(date('Y-m-d', $first_order['createtime']));
        $date = new DateTime(sprintf("%d-%02d-%02d 00:00", $year_str, $month_str, $day_str));
        if ($date < $order_date_obj) {
            $result['title'] .= '*';
            JSON::success($result);
        }
    } catch (Exception $e) {
    }
} else {
    JSON::success($result);
}

$data = Statistics::userMonth($agent, $month, $day_str);
$result = array_merge($result, $data);

JSON::success($result);