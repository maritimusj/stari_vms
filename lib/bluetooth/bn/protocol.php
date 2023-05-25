<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\bn;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class protocol implements IBlueToothProtocol
{

    function getTitle(): string
    {
        return '蓝牙电子烟售货机交互协议(BN v0.1)';
    }

    function transUID($uid)
    {
        return $uid;
    }

    /**
     * $data 携带设备广播的数据
     */
    function onConnected($device_id, $data = ''): ?ICmd
    {
        if ($data) {

        }

        return null;
    }

    function initialize($device_id)
    {
        // TODO: Implement initialize() method.
    }

    function open($device_id, $data): ?ICmd
    {
        // TODO: Implement open() method.
    }

    function parseResponse($device_id, $data): ?IResponse
    {
        // TODO: Implement parseResponse() method.
    }
}