<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wx;

use zovye\Contract\bluetooth\ICmd;

class Helper
{
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