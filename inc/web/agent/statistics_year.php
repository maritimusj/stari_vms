<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\domain\Agent;
use zovye\domain\Order;

$agent_id = Request::int('id');
$agent = Agent::get($agent_id);

if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$year_str = Request::int('year');
$month_str = Request::int('month');

try {
    $year = new DateTime(sprintf("%d-%02d-01", $year_str, $month_str));
} catch (Exception $e) {
    JSON::fail('时间格式不正确！');
    exit();
}

if ($year->getTimestamp() > time()) {
    JSON::fail('时间不能超过当前时间！');
}

$result = [
    'title' => $year->format('Y年'),
    'list' => [],
    'summary' => [],
];

$first_order = Order::getFirstOrderOfAgent($agent);
if (empty($first_order)) {
    $result['year'][] = (new DateTime())->format('Y');
    JSON::success($result);
}

try {
    $order_date_obj = new DateTime(date('Y-m-01', $first_order['createtime']));
    $date = new DateTime("$year_str-$month_str-01 00:00");
    if ($date < $order_date_obj) {
        $result['title'] .= '*';
        JSON::success($result);
    }
} catch (Exception $e) {
}

$data = Statistics::userYear($agent, $year, $month_str);
$result = array_merge($result, $data);

JSON::success($result);
