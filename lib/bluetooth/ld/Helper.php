<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\ld;

use zovye\domain\Device;

class Helper
{
    public static function resetSEQ($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $device->updateSettings('lingdong.seq', 0);
        }
    }

    public static function nextSEQ($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $seq = $device->settings('lingdong.seq', 0);
            if ($seq > 255) {
                $seq = 0;
            } else {
                $seq ++;
            }

            $device->updateSettings('lingdong.seq', $seq);
            return $seq;
        }

        return rand(0, 256);
    }
}