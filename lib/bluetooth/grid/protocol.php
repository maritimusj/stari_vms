<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace bluetooth\grid;

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
    function parseMessage($device_id, $data): ?IResult
    {
        $result =  new result($device_id, $data);
        if ($result->isOpenSuccess()) {
            $device = Device::get($device_id, true);
            if ($device) {
                $device->setBluetoothStatus(Device::BLUETOOTH_CONNECTED);
            }
        }
        return null;
    }

    function getTitle(): string
    {
        return "第三方厂商蓝牙协议 BLE-v3";
    }

    function initialize($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $password = $device->settings("extra.bluetooth.grid.password");
            if ($password) {
                return new Auth($device_id, $password);
            }
        }
        return null;
    }
}