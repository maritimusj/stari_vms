<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\refund;

//订单退款

use Exception;
use zovye\CtrlServ;
use zovye\request;
use zovye\Order;
use zovye\model\orderModelObj;
use zovye\Util;
use function zovye\request;
use function zovye\is_error;

$op = request::op('default');
$order_no = request::str('orderNO');
$num = request::int('num');
$reset_payload = request('reset');

$log = [
    'orderNO' => $order_no,
    'num' => $num,
    'resetPayload' => $reset_payload,
    'message' => urldecode(request('message')),
];

if ($op == 'refund' && CtrlServ::checkJobSign([
        'orderNO' => $order_no,
        'reset' => $reset_payload,
        'num' => $num,
        'message' => request('message'),
    ])) {
    $order = Order::get($order_no, true);
    if (empty($order)) {
        //没有订单，也要尝试退款
        try {
            $log['result'] = Order::refundBy($order_no);
        } catch (Exception $e) {
            $log['exception'] = [
                'type' => get_class($e),
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
            ];
        }
        return Util::logToFile('refund', $log);
    }

    $device = $order->getDevice();
    //蓝牙设备退款
    if ($device && $device->isBlueToothDevice()) {
        if ($order->isBluetoothResultOk()) {
            $log['result'] = '订单已成功，取消退款！';
            return Util::logToFile('refund', $log);
        }

        //退款
        $res = Order::refund($order->getOrderNO(), 0, ['message' => $log['message']]);
        if ($reset_payload && !is_error($res)) {
            resetPayload($order, $num);
        }
        $log['result'] = is_error($res) ? $res : '退款成功！';
        return Util::logToFile('refund', $log);
    }

    //以下是普通设备退款
    if ($order->isPullOk()) {
        $log['result'] = '订单已成功，取消退款！';
        return Util::logToFile('refund', $log);
    }

    //退款
    $res = Order::refund($order->getOrderNO(), $num, ['message' => $log['message']]);
    if ($reset_payload && !is_error($res)) {
        resetPayload($order, $num);
    }

    $log['result'] = is_error($res) ? $res : '退款成功！';
}

Util::logToFile('refund', $log);

function resetPayload(orderModelObj $order, int $num = 0)
{
    $device = $order->getDevice();
    if ($device) {
        $goods_id = $order->getGoodsId();
        $total = $num == 0 ? $order->getNum() : $num;
        $device->resetGoodsNum($goods_id, '+' . $total, "订单退款：{$order->getOrderNO()}");
        $device->save();
    }
}
