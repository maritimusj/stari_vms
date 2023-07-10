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

    private $id;

    /**
     * @param $device_id
     * @param $id
     */
    public function __construct($device_id, $id)
    {
        $this->device_id = $device_id;
        $this->id = $id;
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getID(): string
    {
        return bin2hex($this->getRaw());
    }

    function getData()
    {
        return $this->id;
    }

    function getRaw()
    {
        return pack('C*', 0xa5, 0x02, $this->id, 0x05);
    }

    function getMessage(): string
    {
        return '<= 请求开锁：'.$this->id;
    }

    function getEncoded($fn = null)
    {
        $raw = $this->getRaw();

        return is_callable($fn) ? call_user_func($fn, $raw) : base64_encode($raw);
    }
}