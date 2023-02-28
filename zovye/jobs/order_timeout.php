<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderTimeout;

//订单支付超时处理

use zovye\CtrlServ;
use zovye\Job;
use zovye\Locker;
use zovye\Log;
use zovye\model\pay_logsModelObj;
use zovye\Order;
use zovye\Pay;
use zovye\Request;
use function zovye\request;

$op = Request::op('default');
$order_no = request('orderNO');
$log = [
    'orderNO' => $order_no,
];

if ($op == 'order_timeout' && CtrlServ::checkJobSign(['orderNO' => $order_no])) {
    if (Locker::try("pay:$order_no", REQUEST_ID, 3)) {
        $order = Order::get($order_no, true);
        if (empty($order)) {
            //没有订单，尝试退款
            Job::refund($order_no, '没有找到订单');
            $log['refund'] = '没有订单，尝试退款！';

            /** @var pay_logsModelObj $pay_log */
            $pay_log = Pay::getPayLog($order_no);
            if ($pay_log) {
                $data = $pay_log->getData();
                if ($data) {
                    if (empty($data['cancelled']) && empty($data['payResult']) && empty($data['timeout'])) {
                        $pay_log->setData('timeout', ['createtime' => time()]);
                        $pay_log->save();
                    }
                    $log['data'] = $data;
                    Log::debug('order_timeout', $log);
                    Job::exit();
                }
            }
        } else {
            $log['order'] = [
                'order' => $order->getId(),
                'createdAt' => date('Y-m-d H:i:s', $order->getCreatetime()),
            ];
        }
    } else {
        $log['retry'] = 'lock failed, relaunch orderTimeout job';
        Job::orderTimeout($order_no, 10);
    }
}

Log::debug('order_timeout', $log);
