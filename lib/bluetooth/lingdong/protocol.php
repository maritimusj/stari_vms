<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class protocol implements IBlueToothProtocol
{
    function getTitle(): string
    {
        return 'LD蓝牙售货机协议 v1.4.1';
    }

    function transUID($uid)
    {
        return $uid;
    }

    function onConnected($device_id, $data = ''): ?ICmd
    {
        return new ShakeHandCmd($device_id);
    }

    function initialize($device_id)
    {
        return null;
    }

    function open($device_id, $data): ?ICmd
    {
        $locker = $data['locker'] ?? null;
        if (is_int($locker)) {
            return new OpenDeviceCmd($device_id, $locker);
        }

        return null;
    }

    function parseResponse($device_id, $data): ?IResponse
    {
        return new response($device_id, $data);
    }
}