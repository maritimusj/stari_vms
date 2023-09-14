<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\domain\Account;
use zovye\domain\Order;

$account_id = Request::int('id');
$account = Account::get($account_id);

if (empty($account)) {
    JSON::fail('找不到这个任务！');
}

$first_order = Order::getFirstOrderOfAccount($account);
if (empty($first_order)) {
    JSON::fail('暂时没有任务出货数据！');
}

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