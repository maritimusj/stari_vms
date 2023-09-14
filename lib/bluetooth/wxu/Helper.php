<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wxu;

use zovye\domain\Device;

class Helper
{
    public static function reset($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $device->setQoe(-1);
            $device->updateSettings('wxu.seq', 0);
            $device->save();
        }
    }

    public static function nextSEQ($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $seq = $device->settings('wxu.seq', 0);
            if ($seq > 255) {
                $seq = 0;
            } else {
                $seq ++;
            }

            $device->updateSettings('wxu.seq', $seq);
            return $seq;
        }

        return rand(0, 256);
    }
}