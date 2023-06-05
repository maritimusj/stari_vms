<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\ld;

class OpenDeviceCmd extends cmd
{
    private $locker_id;

    public function __construct($device_id, $locker_id)
    {
        $this->locker_id = $locker_id;
        parent::__construct($device_id, 0x02, [$locker_id, 0, 0, 0, 0]);
    }

    public function getMessage(): string
    {
        return '<= 请求开锁：'.$this->locker_id;
    }
}