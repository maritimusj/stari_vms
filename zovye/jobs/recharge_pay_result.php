<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderPayResult;

//充值支付结果检查

use zovye\CommissionBalance;
use zovye\CtrlServ;
use zovye\Job;
use zovye\JobException;
use zovye\Locker;
use zovye\Log;
use zovye\Pay;
use zovye\request;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;

$order_no = request::str('orderNO');
$start = request::int('start');

$log = [
    'orderNO' => $order_no,
    'start' => $start,
];

if (CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确！', $log);
}

if (!Locker::try("pay:$order_no", REQUEST_ID, 3)) {
    throw new JobException('无法锁定支付记录！', $log);
}

$result = Util::transactionDo(function () use ($order_no, $start, &$log) {
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        return err('找不到这个支付记录!');
    }

    if ($pay_log->isCancelled() || $pay_log->isTimeout() || $pay_log->isRefund()) {
        return err('支付已无效');
    }

    if ($pay_log->isRecharged()) {
        return err('已充值到用户帐户');
    }

    $res = Pay::query($order_no);

    if (is_error($res)) {
        if (time() - $start < 30) {
            //重新加入一个支付结果检查任务
            $log['job schedule'] = Job::rechargePayResult($order_no, $start);
        }

        return err($res['message']);
    }

    $log['res'] = $res;

    if ($res['result'] !== 'success') {
        return err('支付结果不正确');
    }

    $pay_log->setData('queryResult', $res);
    $pay_log->setData('create_order.createtime', time());

    if (!$pay_log->save()) {
        return err('无法保存payResult!');
    }

    $user = $pay_log->getOwner();
    if (empty($user)) {
        return err('找不到指定的用户!');
    }

    $price = $pay_log->getPrice();
    if ($price < 1) {
        return err('支付金额小于1!');
    }

    $balance = $user->getCommissionBalance();
    if (!$balance->change($price, CommissionBalance::RECHARGE)) {
        return err('创建用户帐户记录失败!');
    }

    $pay_log->setData('recharged', [
        'time' => time(),
    ]);

    if (!$pay_log->save()) {
        return err('保存用户数据失败!');
    }

    return true;
});

if (is_error($result)) {
    throw new JobException($result['message'], $log);
}

Log::debug(request::op('job'), $log);
