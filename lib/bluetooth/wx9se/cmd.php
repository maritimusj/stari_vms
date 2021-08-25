<?php

namespace bluetooth\wx9se;

use zovye\Contract\bluetooth\ICmd;

class cmd implements ICmd
{
    private $device_id;
    private $id;
    private $key;
    private $data;

    /**
     * @param $device_id
     * @param $id
     * @param $key
     * @param $data
     */
    public function __construct($device_id, $id, $key, $data)
    {
        $this->device_id = $device_id;
        $this->id = $id;
        $this->key = $key;
        $this->data = $data;
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getID()
    {
        return $this->id;
    }

    function getData()
    {
        return $this->data;
    }

    function getRaw()
    {
        return $this->encode();
    }

    function encode()
    {
        return pack('c*', $this->id, $this->key, $this->data);
    }

    function getMessage(): string
    {
        return protocol::$strMsg[$this->id] ?? '<未知>';
    }

    function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}