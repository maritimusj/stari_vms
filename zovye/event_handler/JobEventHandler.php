<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\orderModelObj;

class JobEventHandler
{
    /**
     * 事件：device.openSuccess 处理程序
     * @param orderModelObj|null $order
     */
    public static function onDeviceOpenSuccess(orderModelObj $order = null)
    {
        if ($order) {
            Job::order($order->getId());

            //订单通知
            $notify = Config::notify('order', []);
            if (!empty($notify['url'])) {
                if (($notify['f'] && $order->isFree()) || ($notify['p'] && $order->isPay())) {
                    $data = [];
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
