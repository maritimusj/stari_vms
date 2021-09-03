<?php

namespace bluetooth\wx9se;

class AppVerifyCMD extends cmd
{
    public function __construct($data)
    {
        parent::__construct(0, protocol::CMD_SHAKE_HAND, protocol::KEY_VERIFY, $data);
    }

}