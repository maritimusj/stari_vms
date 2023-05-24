<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;


class ShakeHandCmd extends cmd
{
    public function __construct($device_id)
    {
        parent::__construct($device_id, 0x01, [0x6C, 0x6F, 0x67, 0x69, 0x6F]);
    }
}