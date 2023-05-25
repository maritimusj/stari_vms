<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\rh;

class OpenDeviceCmd extends cmd
{
    private $lane;
    private $device_id;

    public function __construct($device_id, $lane)
    {
        $this->device_id = $device_id;
        $this->lane = $lane;

        $data = sprintf('%013sA%02d', $device_id, $lane);
        parent::__construct($device_id, protocol::OPEN, $data);
    }

    function getMessage(): string
    {
        return "<= 出货请求，设备：{$this->device_id}，货道：{$this->lane}";
    }
}