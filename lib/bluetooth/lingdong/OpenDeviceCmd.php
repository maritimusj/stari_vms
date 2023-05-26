<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

class OpenDeviceCmd extends cmd
{
    private $locker_id;

    public function __construct($device_id, $locker_id)
    {
        $this->locker_id = $locker_id;
        parent::__construct($device_id, 0x02, [$locker_id, 0x0, 0x0, 0x0, 0x0]);
    }

    function getMessage(): string
    {
        return '<= 请求开锁, '.$this->locker_id;
    }
}