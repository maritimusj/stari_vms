<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\hmb;

use zovye\BlueToothProtocol;
use zovye\contract\bluetooth\IBlueToothProtocol;
use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;

class protocol implements IBlueToothProtocol
{
    function support($fn): bool
    {
        if ($fn == BlueToothProtocol::QOE) {
            return true;
        }

        return false;
    }

    function getTitle(): string
    {
        return '蓝牙售货机协议(hmb v1.0)';
    }

    function transUID($uid)
    {
        return $uid;
    }

    function onConnected($device_id, $data = ''): ?ICmd
    {
        return null;
    }

    function initialize($device_id)
    {
        return null;
    }

    function open($device_id, $data): ?ICmd
    {
        $index = $data['motor'] ?? $data['locker'];

        return new startR($device_id, $index, $data['timeout']);
    }

    function parseResponse($device_id, $data): ?IResponse
    {
        return new response($data);
    }
}