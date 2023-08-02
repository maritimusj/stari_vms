<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refund;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\Order;
use zovye\Request;

$op = Request::op('default');
$log = [
    'id' => request::int('id'),
];

if ($op == 'order_notify' && CtrlServ::checkJobSign(['id' => $log['id']])) {

    //通过微信模板消息给代理商推送消息
    $order = Order::get($log['id']);
    if (empty($order)) {
        throw new JobException('找不到这个订单！', $log);
    }

    $log['result'] = Order::sendTemplateMsg($order);
}

Log::debug('order_notify', $log);
