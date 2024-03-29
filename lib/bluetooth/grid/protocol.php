<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\grid;

use zovye\BlueToothProtocol;
use zovye\contract\bluetooth\IBlueToothProtocol;
use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;
use zovye\domain\Device;

class protocol implements IBlueToothProtocol
{
    function getTitle(): string
    {
        return '第三方厂商蓝牙协议(grid v3)';
    }

    function support($fn): bool
    {
        if ($fn == BlueToothProtocol::QOE) {
            return true;
        }

        return false;
    }

    function transUID($uid)
    {
        return $uid;
    }

    /**
     * @inheritDoc
     */
    function onConnected($device_id, $data = ''): ?ICmd
    {
        $device = Device::get($device_id, true);
        if ($device) {
            return new R($device_id);
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    function open($device_id, $data): ?ICmd
    {
        return new Unlock($device_id, $data['locker']);
    }

    /**
     * @inheritDoc
     */
    function parseResponse($device_id, $data): ?IResponse
    {
        $result =  new response($device_id, $data);
        if ($result->isOpenSuccess()) {
            $device = Device::get($device_id, true);
            if ($device) {
                $device->setBluetoothStatus(Device::BLUETOOTH_CONNECTED);
            }
        }
        return null;
    }

    function initialize($device_id): ?ICmd
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $password = $device->settings('grid.password');
            if ($password) {
                return new Auth($device_id, $password);
            }
        }
        return null;
    }
}