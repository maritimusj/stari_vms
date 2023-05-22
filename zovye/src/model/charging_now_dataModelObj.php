<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\model;

use zovye\base\modelObj;
use zovye\Device;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\User;
use function zovye\tb;

/**
 * @method getSerial();
 * @method getUserId();
 * @method getDeviceId();
 * @method getChargerId();
 * @method getCreatetime();
 */
class charging_now_dataModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('charging_now_data');
    }

    /** @var int */
    protected $id;

    /** @var string */
    protected $serial;

    /** @var int */
    protected $user_id;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $charger_id;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public function getUser(): ?userModelObj
    {
        return User::get($this->user_id);
    }

    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_id);
    }
}