<?php

namespace bluetooth\wx9se;

class ShakeHandCMD extends cmd
{
    public function __construct($device_id, $data)
    {
        parent::__construct($device_id, protocol::CMD_SHAKE_HAND, protocol::KEY_SHAKE, $data);
    }
}