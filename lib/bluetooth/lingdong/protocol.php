<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResult;
use zovye\Device;

class protocol implements IBlueToothProtocol
{

    function transUID($uid)
    {
        return $uid;
    }

    function onConnected($device_id, $data = ''): ?ICmd
    {
        $device = Device::get($device_id, true);
        if ($device) {
            return new ShakeHandCmd($device_id);
        }

        return null;
    }

    function initialize($device_id)
    {
        return null;
    }

    function open($device_id, $data): ?ICmd
    {
        $locker = $data['locker'] ?? null;
        if ($locker) {
            return new OpenDeviceCmd($device_id, $locker);
        }

        return null;
    }

    function parseMessage($device_id, $data): ?IResult
    {
        return new result($device_id, $data);
    }

    function getTitle(): string
    {
        return 'LD蓝牙售货机协议 v1.4.1';
    }
}