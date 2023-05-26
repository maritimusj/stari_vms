<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\rh;

class OpenDeviceCmd extends cmd
{
    private $lane;

    public function __construct($device_id, $lane)
    {
        $this->lane = $lane;

        $data = sprintf('%013sA%02d', $device_id, $lane);
        parent::__construct($device_id, protocol::OPEN, $data);
    }

    public function getMessage(): string
    {
        return "<= 出货请求，货道：$this->lane";
    }
}