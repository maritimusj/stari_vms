<?php

namespace bluetooth\wx;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResult;

class protocol implements IBlueToothProtocol
{
    /**
     * @param $device_id
     * @return ICmd
     */
    public function shakeHandMsg($device_id)
    {
        return new cmd($device_id, cmd::CMD_SHAKE_HAND, [0x6C, 0x6F, 0x67, 0x69, 0x6E]);
    }

    /**
     * @param $device_id
     * @param $lane_id
     * @param int $locker_id
     * @param bool $feedback
     * @return ICmd
     */
    public function openMsg($device_id, $lane_id, $locker_id = 0x00, $feedback = false)
    {
        $act = 0x00;
        if ($lane_id > 0) {
            $act = 0x01;
        }
        if ($lane_id > 60) {
            $lane_id = 0x00;
            $act = 0x00;
        }

        return new cmd($device_id, cmd::CMD_RUN, [$lane_id, $act, $locker_id, 0x00, $feedback ? 0x01 : 0x00]);
    }

    /**
     * @param string $data
     * @param bool $base64encoded
     * @return IResult
     */
    public function parseResult($data = '', $base64encoded = true)
    {
        return new result($data, $base64encoded);
    }

    public function getTitle(): string
    {
        return '第三方厂商蓝牙协议v1.0';
    }

    public function transUID($uid)
    {
        return str_pad($uid, 12, '0', STR_PAD_LEFT);
    }

    //设备已连接

    /**
     * @param $device_id
     * @param string $data
     * @return ICmd
     */
    public function onConnected($device_id, $data = ''): ?ICmd
    {
        return $this->shakeHandMsg($device_id);
    }

    function initialize($device_id)
    {
        return $this->shakeHandMsg($device_id);
    }

    /**
     * @param $device_id
     * @param $data
     * @return IResult
     */
    public function parseMessage($device_id, $data): ?IResult
    {
        return $this->parseResult($data);
    }

    //出货

    /**
     * @param $device_id
     * @param $data
     * @return ICmd
     */
    public function open($device_id, $data): ?ICmd
    {
        if ($data['motor']) {
            return $this->openMsg($device_id, $data['motor'], 0);
        }

        return $this->openMsg($device_id, 0, $data['locker']);
    }
}