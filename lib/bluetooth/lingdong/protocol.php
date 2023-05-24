<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResult;

class protocol implements IBlueToothProtocol
{

    function transUID($uid)
    {
        // TODO: Implement transUID() method.
    }

    function onConnected($device_id, $data = ''): ?ICmd
    {
        // TODO: Implement onConnected() method.
    }

    function initialize($device_id)
    {
        // TODO: Implement initialize() method.
    }

    function open($device_id, $data): ?ICmd
    {
        // TODO: Implement open() method.
    }

    function parseMessage($device_id, $data): ?IResult
    {
        // TODO: Implement parseMessage() method.
    }

    function getTitle(): string
    {
        return 'LD蓝牙售货机协议 v1.4.1';
    }
}