<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\event;

use zovye\App;
use zovye\business\GDCVMachine;
use zovye\Config;
use zovye\CtrlServ;
use zovye\Job;
use zovye\model\orderModelObj;

class JobEventHandler
{
    /**
     * 事件：device.openSuccess 处理程序
     */
    public static function onDeviceOpenSuccess(orderModelObj $order = null)
    {
        if ($order) {
            Job::order($order->getId());
            Job::orderNotify($order);

            if (App::isGDCVMachineEnabled() && $order->isFree()) {
                GDCVMachine::scheduleUploadOrderLogJob($order);
                $device = $order->getDevice();
                if ($device) {
                    GDCVMachine::scheduleUploadDeviceJob($device);
                }
            }

            //订单通知
            $notify = Config::notify('order', []);
            if (!empty($notify['url'])) {
                if (($notify['f'] && $order->isFree()) || ($notify['p'] && $order->isPay())) {
                    $orderData =  [
                        'orderNO' => $order->getOrderNO(),
                        'price' => $order->getCommissionPrice(),
                    ];

                    $goods = $order->getGoodsData();
                    $orderData['goods'] = [
                        'id' => $goods['id'], 
                        'name' => $goods['name'],
                        'img' => $goods['img'],
                        'price' => $goods['price'],
                        'price_formatted' => $goods['price_formatted'],
                        'unit_title' => $goods['unit_title'],
                        'num' => $goods['num'],
                        'balance' => $goods['balance'],
                        'cargo_lane' => $goods['cargo_lane'],
                        'createtime_formatted' => $goods['createtime_formatted'],
                    ];

                    if ($order->isFree()) {
                        $orderData['goods']['is_free'] = true;
                    }
                    if ($order->isPay()) {
                        $orderData['goods']['is_pay'] = true;
                    }
                    
                    $data = [
                        'request_id' => REQUEST_ID,
                        'order' => $orderData,
                    ];

                    $device = $order->getDevice();
                    if ($device) {
                        $data['device'] = $device->profile(true);
                    }

                    $agent = $order->getAgent();
                    if ($agent) {
                        $data['agent'] = $agent->profile();
                    }

                    CtrlServ::httpQueuedCallback(LEVEL_NORMAL, $notify['url'], json_encode($data));
                }
            }
        }
    }
}
