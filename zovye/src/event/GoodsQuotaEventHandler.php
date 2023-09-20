<?php

namespace zovye\event;

use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;
use zovye\ZovyeException;
use function zovye\isEmptyArray;

class GoodsQuotaEventHandler
{
    /**
     * 事件：device.order.created
     */
    public static function onDeviceOrderCreated(deviceModelObj $device, userModelObj $user, orderModelObj $order)
    {
        $goods = $order->getGoods();
        if ($goods) {
            $quota = $goods->getQuota();
            if (!isEmptyArray($quota)) {
                if ($order->isFree()) {
                    $day_limit = $quota['free']['day'];
                    if (!empty($day_limit) && $day_limit < $user->getTodayFreeTotal($goods->getId())) {
                        ZovyeException::throwWith('对不起，该商品免费今日额度已用完！', -1, $device);
                    }
                    $all_limit = $quota['free']['all'];
                    if (!empty($all_limit) && $all_limit < $user->getFreeTotal($goods->getId())) {
                        ZovyeException::throwWith('对不起，该商品免费额度已用完！', -1, $device);
                    }
                } else {
                    $day_limit = $quota['pay']['day'];
                    if (!empty($day_limit) && $day_limit < $user->getTodayPayTotal($goods->getId())) {
                        ZovyeException::throwWith('对不起，该商品今日可用额度已用完！', -1, $device);
                    }
                    $all_limit = $quota['pay']['all'];
                    if (!empty($all_limit) && $all_limit < $user->getPayTotal($goods->getId())) {
                        ZovyeException::throwWith('对不起，该商品可用额度已用完！', -1, $device);
                    }
                }
            }
        }
    }
}