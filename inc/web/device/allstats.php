<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

//全部出货统计
use DateTime;
use Exception;

$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

list($m, $total) = Util::cachedCall(30, function () use ($device) {
    //开始 结束
    $first_order = Order::getFirstOrderOfDevice($device);
    $last_order = Order::getLastOrderOfDevice($device);
    if ($first_order) {
        $first_order_datetime = intval($first_order['createtime']);
    } else {
        $first_order_datetime = time();
    }
    if ($last_order) {
        $last_order_datetime = intval($last_order->getCreatetime());
    } else {
        $last_order_datetime = time();
    }

    $total_num = 0;
    $months = [];
    try {
        $begin = new DateTime(date('Y-m-d H:i:s', $first_order_datetime));
        $end = new DateTime(date('Y-m-d H:i:s', $last_order_datetime));

        $end = $end->modify('last day of this month 00:00');

        $counter = new OrderCounter();

        while ($begin < $end) {
            $result = $counter->getMonthAll([$device, 'goods'], $begin);
            $result['month'] = $begin->format('Y-m');
            $total_num += $result['total'];
            $months[$begin->format('Y年m月')] = $result;
            $begin->modify('first day of next month 00:00');
        }

        return [$months, $total_num];

    } catch (Exception $e) {
    }

    return [];
}, $device->getId());

$content = app()->fetchTemplate(
    'web/device/all_stats',
    [
        'device' => $device,
        'm_all' => $m,
        'total' => $total,
        'device_id' => $device->getId(),
    ]
);

JSON::success(['title' => "<b>{$device->getName()}</b>的出货统计", 'content' => $content]);