<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\orderPayResult;

//订单支付结果检查

use DateTime;
use Exception;
use zovye\CtrlServ;
use zovye\Device;
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
    throw new JobException('签名不正确!', $log);
}

if (!Locker::try("pay:$order_no", REQUEST_ID, 3)) {
    throw new JobException('锁定支付失败!', $log);
}

$pay_log = Pay::getPayLog($order_no, LOG_DEVICE_RENEWAL_PAY);
if (empty($pay_log)) {
    throw new JobException('找不到支付记录!', $log);
}

if (!$pay_log->isPaid()) {
    $res = Pay::queryFor($pay_log);

    $log['res'] = $res;

    if (is_error($res)) {
        if (time() - $start < 30) {
            //重新加入一个支付结果检查任务
            $log['job schedule'] = Job::deviceRenewalPayResult($order_no, $start);
        }
        throw new JobException($res['message'], $log);
    }

    if ($res['result'] !== 'success') {
        $log['error'] = '支付结果不正确！';
        throw new JobException('支付结果不正确！', $log);
    }

    $pay_log->setData('queryResult', $res);

    if (!$pay_log->save()) {
        throw new JobException('无法保存payResult!', $log);
    }

    $device_id = $pay_log->getDeviceId();

    $device = Device::get($device_id);
    if (!$device) {
        throw new JobException('找不到指定的设备!', $log);
    }

    $expiration = $device->getExpiration();
    try {
        $time = new DateTime($expiration);
    } catch (Exception $e) {
        $time = new DateTime();
    }

    $years = $pay_log->getData('years', 0);
    $time->modify("+$years year");

    $device->setExpiration($time->format('Y-m-d'));
    $device->save();
}

Log::debug(request::op('job'), $log);