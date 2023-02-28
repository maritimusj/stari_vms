<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refund;

//订单退款

use Exception;
use zovye\CtrlServ;
use zovye\Helper;
use zovye\Job;
use zovye\Locker;
use zovye\Log;
use zovye\model\orderModelObj;
use zovye\Order;
use zovye\Request;
use zovye\State;
use function zovye\error;
use function zovye\is_error;
use function zovye\request;

$op = Request::op('default');
$order_no = Request::str('orderNO');
$num = Request::int('num');
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

    if (!Locker::try("pay:$order_no", REQUEST_ID, 3)) {
        $log['relaunch refund job'] = Job::refund($order_no, request('message'), $num, $reset_payload, 10);
        Log::debug('refund', $log);
        Job::exit();
    }

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
        Log::debug('refund', $log);
        Job::exit();
    }

    $device = $order->getDevice();
    if ($device) {
        //蓝牙设备退款
        if ($device->isBlueToothDevice()) {
            if ($order->isBluetoothResultOk()) {
                $log['result'] = '订单已成功，取消退款！';
                Log::debug('refund', $log);
                Job::exit();
            }

            //退款
            $res = Order::refund($order->getOrderNO(), 0, ['message' => $log['message']]);
            if ($reset_payload && !is_error($res)) {
                resetPayload($order, $num);
            }

            $log['result'] = is_error($res) ? $res : '退款成功！';
            Log::debug('refund', $log);
            Job::exit();

        } elseif ($device->isChargingDevice()) {
            if ($order->isChargingFinished()) {
                try {
                    $log['result'] = Order::refundBy($order_no, 0 - $order->getPrice());
                } catch (Exception $e) {
                    $log['exception'] = [
                        'type' => get_class($e),
                        'code' => $e->getCode(),
                        'msg' => $e->getMessage(),
                    ];
                }
            } else {
                $log['err'] = '充电订单未结束！';
            }
            Log::debug('refund', $log);
            Job::exit();
        } elseif ($device->isFuelingDevice()) {
            if ($order->isFuelingFinished()) {
                try {
                    $res = Order::refundBy($order_no, 0 - $order->getPrice());
                    $order->setExtraData('fueling.refund', $res);
                    $order->save();
                    $log['result'] = $res;
                } catch (Exception $e) {
                    $log['exception'] = [
                        'type' => get_class($e),
                        'code' => $e->getCode(),
                        'msg' => $e->getMessage(),
                    ];
                }
            } else {
                $log['err'] = '加注订单未结束！';
            }
            Log::debug('refund', $log);
            Job::exit();
        }
    }

    //以下是普通设备退款
    if (empty($num) && $order->isPullOk()) {
        $log['result'] = '订单已成功，取消退款！';
        Log::debug('refund', $log);
        Job::exit();
    }

    $res = [];

    if ($num >= 0) {
        //退款
        $res = Order::refund($order->getOrderNO(), $num, ['message' => $log['message']]);
        if ($reset_payload && !is_error($res)) {
            resetPayload($order, $num);
        }
    } else {
        //找出所有出货失败的商品，并计算退款金额
        $price = 0;

        $list = Helper::getOrderPullLog($order);
        foreach ($list as $entry) {
            if (is_error($entry['result'])) {
                $price += intval($entry['price']);
            }
        }

        if ($price > 0) {
            //退款
            $res = Order::refund2($order->getOrderNO(), $price, ['message' => $log['message']]);
            if ($reset_payload && !is_error($res)) {
                //恢复指定商品库存
                resetPayload2($order);
            }
        }
    }

    $log['result'] = !is_error($res) ? '退款成功！' : $res;
}

Log::debug('refund', $log);

function resetPayload(orderModelObj $order, int $num = 0): array
{
    $device = $order->getDevice();
    if ($device) {
        $goods_id = $order->getGoodsId();
        $total = $num == 0 ? $order->getNum() : $num;

        $locker = $device->payloadLockAcquire(10);
        if ($locker) {
            $device->resetGoodsNum($goods_id, '+'.$total, "订单退款：{$order->getOrderNO()}");
        } else {
            return error(State::ERROR, '锁定设备库存失败!');
        }

        $locker->unlock();
        if ($device->save()) {
            return ['msg' => '库存已重置！'];
        }
    }

    return ['msg' => '库存重置失败！'];
}

function resetPayload2(orderModelObj $order): array
{
    $device = $order->getDevice();
    if ($device) {
        $locker = $device->payloadLockAcquire(10);
        if (empty($locker)) {
            return error(State::ERROR, '锁定设备库存失败!');
        }

        $result = Helper::getOrderPullLog($order);
        foreach ($result as $entry) {
            $result = $device->resetGoodsNum($entry['goods']['id'], '+1', "订单退款：{$order->getOrderNO()}");
            if (is_error($result)) {
                return $result;
            }
        }

        $locker->unlock();
        if ($device->save()) {
            return ['msg' => '库存已重置！'];
        }
    }

    return ['msg' => '库存重置失败！'];
}