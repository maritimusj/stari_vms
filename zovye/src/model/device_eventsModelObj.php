<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Device;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * Class device_eventsModelObj
 * @package zovye
 * @method getDeviceUid()
 * @method getEvent()
 * @method getCreatetime()
 * @method getExtra()
 */
class device_eventsModelObj extends ModelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $device_uid;
    protected $event;
    protected $extra;
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($read_or_write): string
    {
        return tb('device_events');
    }

    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_uid, true);
    }
}