<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

class OpenDeviceCmd extends cmd
{
    public function __construct($device_id, $locker_id)
    {
        parent::__construct($device_id, $locker_id, [0x6C, 0x0, 0x0, 0x0, 0x0]);
    }
}