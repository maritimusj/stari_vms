<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\rh;

use zovye\contract\bluetooth\ICmd;

class cmd implements ICmd
{
    private $device_id;

    private $id;
    private $data;

    public function __construct($device_id, $id, $data)
    {
        $this->device_id = $device_id;
        $this->id = $id;
        $this->data = $data;
    }

    public function getDeviceID()
    {
        return $this->device_id;
    }

    public function getID()
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getRaw()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        if ($this->id == protocol::OPEN) {
            return '<= 出货请求';
        }

        return '<= 未知请求';
    }

    function encode()
    {
        return Helper::encrypt($this->device_id, $this->data);
    }

    public function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}