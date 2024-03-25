<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderTimeout;

defined('IN_IA') or exit('Access Denied');

//订单支付超时处理

use zovye\CtrlServ;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\model\pay_logsModelObj;
use zovye\Pay;
use zovye\Request;

$order_no = Request::str('orderNO');
$log = [
    'orderNO' => $order_no,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

if (!Locker::try("pay:$order_no", REQUEST_ID, 3)) {

    $log['retry'] = 'lock failed, relaunch orderTimeout job';
    $log['job'] = Job::orderTimeout($order_no, 10);
    Log::debug('order_timeout', $log);

    return;
}

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
                //关闭订单
                $log['close_order'] = Pay::close($order_no);
                $pay_log->setData('close_order', $log['close_order']);
                //记录超时
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

Log::debug('order_timeout', $log);
