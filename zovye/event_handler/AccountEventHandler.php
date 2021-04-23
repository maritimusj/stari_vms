<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Exception;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\model\accountModelObj;

class AccountEventHandler
{
    /**
     * 事件：device.beforeLock
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param accountModelObj|null $account
     * @throws Exception
     */
    public static function onDeviceBeforeLock(deviceModelObj $device, userModelObj $user, accountModelObj $account = null)
    {
        if ($account != null) {
            $res = Util::isAvailable($user, $account, $device);
            if (is_error($res)) {
                $user->remove('last');
                throw new Exception($res['message'], State::ERROR);
            }
        }
    }
}