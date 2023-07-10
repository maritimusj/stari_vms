<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\hlb;

use zovye\Contract\bluetooth\ICmd;

class unlock implements ICmd
{
    private $device_id;

    private $locker_id;

    /**
     * @param $device_id
     * @param $locker_id
     */
    public function __construct($device_id, $locker_id)
    {
        $this->device_id = $device_id;
        $this->locker_id = $locker_id;
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getID(): string
    {
        return '';
    }

    function getData()
    {
        return $this->locker_id;
    }

    function getRaw()
    {
        return pack('c*', 0xa5, 0x02, $this->locker_id, 0x05);
    }

    function getMessage(): string
    {
        return '<= 请求开锁：'.$this->locker_id;
    }

    function getEncoded($fn = null)
    {
        $raw = $this->getRaw();

        return is_callable($fn) ? call_user_func($fn, $raw) : base64_encode($raw);
    }
}