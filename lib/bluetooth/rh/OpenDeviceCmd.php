<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\rh;

class OpenDeviceCmd extends cmd
{
    public function __construct($device_id, $lane)
    {
        $data = sprintf('%013sA%02d', $device_id, $lane);
        parent::__construct($device_id, protocol::OPEN, $data);
    }
}