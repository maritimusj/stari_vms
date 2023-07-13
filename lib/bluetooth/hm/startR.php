<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\hm;

use zovye\Contract\bluetooth\ICmd;

class startR implements ICmd
{
    private $device_id;

    private $index;

    private $timeout;

    /**
     * @param $device_id
     * @param $index
     * @param $timeout
     */
    public function __construct($device_id, $index, $timeout)
    {
        $this->device_id = $device_id;
        $this->index = $index;
        $this->timeout = $timeout;
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
        return $this->index;
    }

    function getRaw()
    {
        return pack('c*', 0xa5, 0x02, $this->index, $this->timeout);
    }

    function getMessage(): string
    {
        return '<= 启动电机：'.$this->index;
    }

    function getEncoded($fn = null)
    {
        $raw = $this->getRaw();

        return is_callable($fn) ? call_user_func($fn, $raw) : base64_encode($raw);
    }
}