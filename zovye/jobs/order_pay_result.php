<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderPayResult;

//订单支付结果检查

use zovye\CtrlServ;
use zovye\Device;
use zovye\Job;
use zovye\Log;
use zovye\Order;
use zovye\Pay;
use zovye\Request;
use function zovye\is_error;

$op = Request::op('default');
$order_no = Request::str('orderNO');
$start = Request::int('start');

$log = [
    'orderNO' => $order_no,
    'start' => $start,
];

if ($op == 'order_pay_result' && CtrlServ::checkJobSign(['orderNO' => $order_no, 'start' => $start])) {
    $order = Order::get($order_no, true);
    if ($order) {
        $log['result'] = '订单已创建！';
        writeLogAndExit($log);
    }

    $pay_log = Pay::getPayLog($order_no);
    if ($pay_log) {
        if ($pay_log->isCancelled() || $pay_log->isTimeout() || $pay_log->isRefund()) {
            $log['result'] = '支付已无效';
            writeLogAndExit($log);
        }
    }

    $res = Pay::query($order_no);
    if (is_error($res)) {
        if (time() - $start < 300) {
            //重新加入一个支付结果检查任务
            $log['job schedule'] = Job::orderPayResult($order_no, $start);
        } else {
            //5分钟检查订单并执行退款
            $log['refund'] = Job::refund($order_no, '获取支付结果失败，订单超时！', 0, false, 300);
        }
        $log['error'] = $res;
        writeLogAndExit($log);
    }

    $log['result'] = $res;
    if ($res['result'] !== 'success') {
        $log['error'] = '支付结果不正确！';
        writeLogAndExit($log);
    }

    $device = Device::get($res['deviceUID'], true);
    if (empty($device)) {
        $log['error'] = "找不到指定设备[{$res['deviceUID']}]";
        writeLogAndExit($log);
    }

    $pay_log->setData('queryResult', $res);
    $pay_log->setData('create_order.createtime', time());
    if (!$pay_log->save()) {
        $log['error'] = '无法保存payResult';
        writeLogAndExit($log);
    }

    //创建一个回调执行创建订单，出货任务
    $res = Job::createOrder($order_no, $device);
    if (!$res) {
        $log['error'] = '创建订单任务失败！';
    } else {
        $log['job'] = '已启动订单任务！';
    }

    writeLogAndExit($log);
}


function writeLogAndExit($log)
{
    Log::debug('order_pay_result', $log);
    Job::exit();
}
