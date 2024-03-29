<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\contract;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

interface IAccountProvider
{
    static function getUID();

    static function fetch(deviceModelObj $device, userModelObj $user);
}