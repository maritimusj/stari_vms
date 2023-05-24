<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\grid;

class Unlock extends cmd
{
    private $lane;

    public function __construct($device_id, $lane = 1)
    {
        parent::__construct($device_id);
        $this->lane = $lane;
    }

    function getID(): string
    {
        return "UNLOCK";
    }

    function getRaw(): string
    {
        return "+OPEN:$this->lane\r\n";
    }

    function getMessage(): string
    {
        return "=> 请求开锁";
    }
}