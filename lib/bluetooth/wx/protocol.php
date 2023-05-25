<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class protocol implements IBlueToothProtocol
{
    public function getTitle(): string
    {
        return '第三方厂商蓝牙协议(v1.0)';
    }

    public function transUID($uid): string
    {
        return str_pad($uid, 12, '0', STR_PAD_LEFT);
    }

    /**
     * @param $device_id
     * @param $data
     * @return IResponse
     */
    public function parseResponse($device_id, $data): ?IResponse
    {
        return new response($data, true);
    }

    /**
     * 设备已连接
     * @param $device_id
     * @param string $data
     * @return ICmd
     */
    public function onConnected($device_id, $data = ''): ?ICmd
    {
        return self::newShakeHandMsg($device_id);
    }

    function initialize($device_id)
    {
        return self::newShakeHandMsg($device_id);
    }

    /**
     * 出货
     * @param $device_id
     * @param $data
     * @return ICmd
     */
    public function open($device_id, $data): ?ICmd
    {
        if ($data['motor']) {
            return self::newOpenMsg($device_id, $data['motor'], 0);
        }

        return self::newOpenMsg($device_id, 0, $data['locker']);
    }

    /**
     * @param $device_id
     * @return ICmd
     */
    public static function newShakeHandMsg($device_id)
    {
        return new cmd($device_id, cmd::CMD_SHAKE_HAND, [0x6C, 0x6F, 0x67, 0x69, 0x6E]);
    }

    /**
     * @param $device_id
     * @param $lane_id
     * @param mixed $locker_id
     * @param bool $feedback
     * @return ICmd
     */
    public static function newOpenMsg($device_id, $lane_id, $locker_id = 0x00, bool $feedback = false)
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
}