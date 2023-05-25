<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\rh;

use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;
use zovye\Device;
use zovye\model\deviceModelObj;

class protocol implements IBlueToothProtocol
{
    const CODE = [0xB8, 0x48, 0xC5, 0xE2];

    const RESULT = 'result';
    const VOLTAGE = 'voltage';
    const SECRET = 'secret';
    const OPEN = 'open';

    public static function getRandomKey($device_id)
    {
        /** @var deviceModelObj $device */
        $device = Device::get($device_id, true);
        if ($device) {
            $v = $device->settings('RH.random_key', '');
            if ($v) {
                return hex2bin($v);
            }
        }

        return '';
    }

    public static function isReady($device_id): bool
    {
        return !empty(self::getRandomKey($device_id));
    }

    public static function encrypt($device_id, $data)
    {
        $key = self::getEncryptKey($device_id);

        //设备需要的数据长度为16?
        return substr(openssl_encrypt($data, 'aes-128-ecb', $key, OPENSSL_RAW_DATA), 0, 16);
    }

    public static function getEncryptKey($device_id): string
    {
        $key = self::getRandomKey($device_id);

        return substr($key, 0, 3).substr($device_id, -6).substr($key, -3).pack('C*', ...self::CODE);
    }

    function getTitle(): string
    {
        return 'RH五格蓝牙设备协议 v1.0';
    }

    function transUID($uid)
    {
        return $uid;
    }

    function onConnected($device_id, $data = ''): ?ICmd
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

                $device->updateSettings('RH.random_key', $key);
                $device->save();

                Device::createBluetoothEventLog($device, $response);
            }
        }

        return null;
    }

    function initialize($device_id)
    {
    }

    function open($device_id, $data): ?ICmd
    {
        return new OpenDeviceCmd($device_id, $data['locker']);
    }

    function parseResponse($device_id, $data): ?IResponse
    {
        return new response($device_id, $data);
    }
}