<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wxu;


class ShakeHandCmd extends cmd
{
    public function __construct($device_id)
    {
        Helper::resetSEQ($device_id);
        parent::__construct($device_id, 0x01, [0x5C, 0x5F, 0x57, 0x59, 0x5E]);
    }
}