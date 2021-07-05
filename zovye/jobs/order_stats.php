<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\orderStats;

use zovye\CtrlServ;
use zovye\Locker;
use zovye\request;
use zovye\Job;
use zovye\model\orderModelObj;
use zovye\Order;
use zovye\Util;
use function zovye\request;

$op = request::op('default');
$log = [
    'id' => request('id'),
];

if ($op == 'order_stats' && CtrlServ::checkJobSign(['id' => request('id')])) {

    $id = request::int('id');
    if ($id > 0) {

        $order = Order::findOne(['id' => $id, 'updatetime' => 0]);
        if ($order) {
            $log['result'] = Util::orderStatistics($order);
        }

    } else {
        $locker = Locker::try("order::statistics", 3);
        if ($locker) {
            //未处理订单
            $other_order = Order::query(
                [
                    'updatetime' => 0,
                    'id <>' => $id,
                ]
            )->limit(100);

            /** @var orderModelObj $entry */
            foreach ($other_order->findAll() as $entry) {
                if ($entry) {
                    $log['statistics'][$entry->getId()] = Util::orderStatistics($entry) ?: 'success';
                }
            }
        } else {
            $log['error'] = 'lock() failed';
        }
    }

    //更多未处理订单
    $order = Order::findOne(['updatetime' => 0]);
    if ($order) {
        Job::orderStatsRepair();
    }
}

Util::logToFile('order_stats', $log);
