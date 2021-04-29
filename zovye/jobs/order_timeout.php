<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\orderTimeout;

//订单支付超时处理

use zovye\CtrlServ;
use zovye\Device;
use zovye\request;
use zovye\Job;
use zovye\model\pay_logsModelObj;
use zovye\Order;
use zovye\Pay;
use zovye\Util;
use function zovye\request;

$op = request::op('default');
$order_no = request('orderNO');
$log = [
    'orderNO' => $order_no,
];

if ($op == 'order_timeout' && CtrlServ::checkJobSign(['orderNO' => $order_no])) {
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
                return Util::logToFile('order_timeout', $log);
            }
        }
    }
}

Util::logToFile('order_timeout', $log);
