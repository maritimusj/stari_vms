<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;
use zovye\Device;

class protocol implements IBlueToothProtocol
{
    public function getTitle(): string
    {
        return '蓝牙售货机协议(LD v1.4.1)';
    }

    public function transUID($uid)
    {
        return $uid;
    }

    public function onConnected($device_id, $data = ''): ?ICmd
    {
        return new ShakeHandCmd($device_id);
    }

    public function initialize($device_id): ?ICmd
    {
        return new ShakeHandCmd($device_id);
    }

    public function open($device_id, $data): ?ICmd
    {
        $locker = $data['locker'] ?? null;
        
        if (is_int($locker)) {
            return new OpenDeviceCmd($device_id, $locker);
        }

        return null;
    }

    public function parseResponse($device_id, $data): ?IResponse
    {
        return new response($device_id, $data);
    }

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