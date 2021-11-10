<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Device;
use zovye\base\modelObj;
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
class device_eventsModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    protected $device_uid;
    protected $event;
    protected $extra;
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('device_events');
    }

    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_uid, true);
    }
}