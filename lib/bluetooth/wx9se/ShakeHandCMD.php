<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx9se;

class ShakeHandCMD extends cmd
{
    public function __construct($device_id, $data)
    {
        $data[] = 0;
        $data[] = 0;
        parent::__construct($device_id, protocol::CMD_SHAKE_HAND, protocol::KEY_SHAKE, $data);
    }
}