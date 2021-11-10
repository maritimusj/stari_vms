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
        }
    }
}
