<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\rh;

use zovye\Device;
use zovye\model\deviceModelObj;

class Helper
{
    const CODE = [0xB8, 0x48, 0xC5, 0xE2];

    public static function setRandomKey(deviceModelObj $device, $key)
    {
        $device->updateSettings('RH.random_key', bin2hex($key));
        $device->save();
    }

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

    public static function getEncryptKey($device_id): string
    {
        $key = self::getRandomKey($device_id);

        return substr($key, 0, 3).substr($device_id, -6).substr($key, -3).pack('C*', ...self::CODE);
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
}