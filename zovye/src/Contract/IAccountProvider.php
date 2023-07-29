<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\Contract;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

interface IAccountProvider
{
    static function getUid();

    static function fetch(deviceModelObj $device, userModelObj $user);
}