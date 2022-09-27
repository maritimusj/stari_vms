<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderPayResult;

//充值支付结果检查

use zovye\CtrlServ;
use zovye\Job;
use zovye\JobException;
use zovye\Locker;
use zovye\Log;
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
    throw new JobException('签名不正确！', $log);
}

if (!Locker::try("pay:$order_no", REQUEST_ID, 3)) {
    throw new JobException('无法锁定支付记录！', $log);
}

$pay_log = Pay::getPayLog($order_no, LOG_RECHARGE);
if (empty($pay_log)) {
    throw new JobException('找不到这个支付记录!', $log);
}

if (!$pay_log->isPaid()) {
    $res = Pay::query($order_no);

    if (is_error($res)) {
        if (time() - $start < 100) {
            //重新加入一个支付结果检查任务
            $log['job schedule'] = Job::rechargePayResult($order_no, $start);
        }
        throw new JobException($res['message'], $log);
    }

    $log['res'] = $res;

    if ($res['result'] !== 'success') {
        throw new JobException('支付结果不正确!', $log);
    }

    $pay_log->setData('queryResult', $res);
    $pay_log->setData('create_order.createtime', time());

    if (!$pay_log->save()) {
        throw new JobException('无法保存payResult!', $log);
    }
}

$user = $pay_log->getOwner();
if (empty($user)) {
    throw new JobException('找不到指定的用户!', $log);
}

$res = $user->recharge($pay_log);
if (is_error($res) && $res['errno'] < 0) {
    throw new JobException($res['message'], $log);
}

Log::debug(request::op('job'), $log);
