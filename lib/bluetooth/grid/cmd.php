<?php

namespace bluetooth\grid;

use zovye\Contract\bluetooth\ICmd;

abstract class cmd implements ICmd
{
    private $device_id;

    public function __construct($device_id)
    {
        $this->device_id = $device_id;
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getData()
    {
        return $this->getRaw();
    }

    function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->getRaw()) : base64_encode($this->getRaw());
    }
}