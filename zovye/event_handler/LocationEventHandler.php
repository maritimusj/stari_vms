<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\LocationUtil;

class LocationEventHandler
{
    /**
     * 事件：device.locked
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param accountModelObj|null $account
     * @throws Exception
     */
    public static function onDeviceLocked(deviceModelObj $device, userModelObj $user, accountModelObj $account = null)
    {
        if ($account != null) {
            //检查用户定位
            if (LocationUtil::mustValidate($user, $device)) {
                $user->remove('last');
                throw new Exception('定位超时，请重新扫描设备二维码 [605]', State::ERROR);
            }
        }
    }
}
