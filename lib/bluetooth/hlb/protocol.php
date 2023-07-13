<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\hlb;

use zovye\BlueToothProtocol;
use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

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
        return '蓝牙售货机协议(hlb v1.0)';
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
        return new unlock($device_id, intval($data['locker']));
    }

    function parseResponse($device_id, $data): ?IResponse
    {
        return new response($data);
    }
}