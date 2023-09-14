<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\rh;

use zovye\BlueToothProtocol;
use zovye\contract\bluetooth\IBlueToothProtocol;
use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;
use zovye\domain\Device;
use zovye\model\deviceModelObj;

class protocol implements IBlueToothProtocol
{
    const RESULT = 'result';
    const VOLTAGE = 'voltage';
    const SECRET = 'secret';
    const OPEN = 'open';
    const UNKNOWN = 'unknown';

    public function getTitle(): string
    {
        return '五格蓝牙设备协议(rh v1.0)';
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
        /** @var deviceModelObj $device */
        $device = Device::get($device_id, true);
        if ($device) {
            if ($data) {
                $response = new response($device_id, $data);

                if ($response->getID() == self::SECRET) {
                    $key = $response->getRawData();
                } else {
                    $key = '';
                }

                Helper::setRandomKey($device, $key);

                Device::createBluetoothEventLog($device, $response);
            }
        }

        return null;
    }

    public function initialize($device_id)
    {
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