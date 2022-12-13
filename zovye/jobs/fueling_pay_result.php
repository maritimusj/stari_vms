<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderPayResult;

//订单支付结果检查

use zovye\CtrlServ;
use zovye\Fueling;
use zovye\Job;
use zovye\JobException;
use zovye\Locker;
use zovye\Log;
use zovye\Order;
use zovye\Pay;
use zovye\request;
use function zovye\is_error;

$order_no = request::str('orderNO');
$start = request::int('start');

$log = [
    'orderNO' => $order_no,
    'start' => $start,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

if (!Locker::try("pay:$order_no", REQUEST_ID, 3)) {
    throw new JobException('锁定支付失败!', $log);
}

$order = Order::get($order_no, true);
if ($order) {
    throw new JobException('订单已创建!', $log);
}

$pay_log = Pay::getPayLog($order_no, LOG_FUELING_PAY);
if (empty($pay_log)) {
    throw new JobException('找不到支付记录!', $log);
}

if (!$pay_log->isPaid()) {
    $res = Pay::query($order_no);
    if (is_error($res)) {
        if (time() - $start < 30) {
            //重新加入一个支付结果检查任务
            $log['job schedule'] = Job::fuelingPayResult($order_no, $start);
        } else {
            //5分钟后检查订单并执行退款
            Job::refund($order_no, '获取支付结果失败，订单超时！', 0, false, 300);
        }

        throw new JobException($res['message'], $log);
    }

    $log['res'] = $res;

    if ($res['result'] !== 'success') {
        $log['error'] = '支付结果不正确！';
        throw new JobException('支付结果不正确！', $log);
    }   
    
    $pay_log->setData('queryResult', $res);
    $pay_log->setData('create_order.createtime', time());

    if (!$pay_log->save()) {
        throw new JobException('无法保存payResult!', $log);
    }
}

if ($pay_log->isFueling()) {
    throw new JobException('已经开始加注!', $log);
}

try {
    $log['res'] = Fueling::startFromPayLog($pay_log);
    if (is_error($log['res'])) {
        throw new JobException($log['res']['message'], $log);
    }

} catch (JobException $e) {
    Job::refund($order_no, "启动设备失败：{$e->getMessage()}");
    throw $e;
}

Log::debug(request::op('job'), $log);

