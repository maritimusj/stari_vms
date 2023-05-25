<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\rh;

use zovye\Contract\bluetooth\ICmd;

class cmd implements ICmd
{
    private $device_id;

    private $uid;
    private $data;

    public function __construct($device_id, $uid, $data)
    {
        $this->device_id = $device_id;
        $this->uid = $uid;
        $this->data = $data;
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getID()
    {
        return $this->uid;
    }

    function getData()
    {
        return $this->data;
    }

    function getRaw()
    {
        return $this->data;
    }

    function getMessage(): string
    {
        if ($this->uid == protocol::OPEN) {
            return '<= 出货请求';
        }

        return '<= 未知请求';
    }

    function encode()
    {
        return protocol::encrypt($this->device_id, $this->data);
    }

    function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}