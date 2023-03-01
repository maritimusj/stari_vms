<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;

$agent_id = Request::int('id');
$agent = Agent::get($agent_id);

if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$first_order = Order::getFirstOrderOfAgent($agent);
if ($first_order) {
    try {
        $begin = new DateTime(date('Y-m-d 00:00:00', $first_order['createtime']));
    } catch (Exception $e) {
        JSON::fail('订单数据不正确！');
    }

    $nextYear = new DateTime('first day of Jan next year 00:00');
    $today = new DateTime();
    if ($nextYear > $today) {
        $nextYear = $today;
    }

    $result = [];
    while ($begin < $nextYear) {
        $year = $begin->format('Y');
        $result[$year][] = $begin->format('m');
        $begin->modify('first day of next month');
    }

    JSON::success($result);
}

JSON::fail('暂时没有任务出货数据！');
