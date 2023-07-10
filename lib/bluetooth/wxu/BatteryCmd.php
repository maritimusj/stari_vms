<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wxu;

class BatteryCmd extends cmd
{
    public function __construct($device_id)
    {
        parent::__construct($device_id, 0x06, [0, 0, 0, 0, 0]);
    }

    public function getMessage(): string
    {
        return '<= 查询电量';
    }
}