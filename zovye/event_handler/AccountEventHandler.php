<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\model\accountModelObj;
use zovye\model\orderModelObj;

class AccountEventHandler
{
    /**
     * 事件：device.beforeLock
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param accountModelObj|null $account
     * @param orderModelObj|null $order
     */
    public static function onDeviceBeforeLock(deviceModelObj $device, userModelObj $user, accountModelObj $account = null, orderModelObj $order = null)
    {
        if ($account && empty($order)) {
            //检查用户是否允许
            $res = Util::isAvailable($user, $account, $device);
            if (is_error($res)) {
                ZovyeException::throwWith($res['message'], -1, $device);
            }
        }
    }
}