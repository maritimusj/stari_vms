<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wxu;

use zovye\BlueToothProtocol;
use zovye\contract\bluetooth\IBlueToothProtocol;
use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;

class protocol implements IBlueToothProtocol
{
    public function getTitle(): string
    {
        return '蓝牙售货机协议(wxu v1.1)';
    }

    function support($fn): bool
    {
        if ($fn == BlueToothProtocol::QOE) {
            return true;
        }

        return false;
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
        return null;
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
}