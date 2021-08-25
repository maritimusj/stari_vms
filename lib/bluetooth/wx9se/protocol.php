<?php

namespace bluetooth\wx9se;

use mermshaus\CRC\CRC16XModem;
use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResult;
use zovye\Device;

class protocol implements IBlueToothProtocol
{
    const MSG_LEN = 20;

    const CMD_SHAKE_HAND = 0x09;
    const CMD_QUERY = 0x02;
    const CMD_CONFIG = 0x03;
    const CMD_NOTIFY = 0x04;
    const CMD_TEST = 0x89;

    public static $strMsg = [
        self::CMD_SHAKE_HAND => '=> 握手',
        self::CMD_QUERY => '=> 获取',
        self::CMD_CONFIG => '=>　设置',
        self::CMD_NOTIFY => '=> 消息',
        self::CMD_TEST => '=> 测试',
    ];

    //CMD_SHAKE_HAND 相关KEY
    const KEY_SHAKE = 0x01;
    const KEY_VERIFY = 0x02;

    //CMD_CONFIG 相关KEY
    const KEY_LOCKER = 0x12;
    const KEY_TIMER = 0x13;
    const KEY_LIGHTS = 0x14;

    const RESULT_LOCKER_SUCCESS = 2;

    //CMD_QUERY 相关KEY
    const KEY_INFO = 0x01;
    const KEY_BATTERY = 0x11;
    const KEY_TIME = 0x13;
    const KEY_LIGHTS_SCHEDULE = 0x14;


    function transUID($uid)
    {
        return $uid;
    }

    /**
     * @inheritDoc
     */
    function onConnected($device_id, $data = ''): ?ICmd
    {
        $data = [];
        for ($i = 0; $i < 16; $i++) {
            $data[] = rand(0, 127);
        }

        //保存握手随机数值
        $device = Device::get($device_id, true);
        if ($device) {
            $device->updateSettings('wx9se.code', $data);
        }

        $data[] = 0;
        $data[] = 0;

        return new cmd($device_id, self::CMD_SHAKE_HAND, self::KEY_SHAKE, $data);
    }

    function initialize($device_id)
    {

    }

    /**
     * @inheritDoc
     */
    function open($device_id, $data): ?ICmd
    {
        // TODO: Implement open() method.
        return null;
    }

    /**
     * @inheritDoc
     */
    function parseMessage($device_id, $data): ?IResult
    {
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return null;
        }

        $result = new result($device_id, $data);

        $cmd = $result->getCmd();
        $key = $result->getKey();

        switch ($cmd) {
            case self::CMD_SHAKE_HAND:
                if ($key == self::KEY_SHAKE) {
                    $code = $device->settings('wx9se.code', []);

                } elseif ($key == self::KEY_VERIFY) {

                }
                break;
            case self::CMD_CONFIG:
                break;
            case self::CMD_QUERY:
                if ($key == self::KEY_INFO) {
                    $version = $result->getVersion();
                    $device->updateSettings('wx9se.ver', $version);
                } elseif ($key == self::KEY_BATTERY) {
                    $v = $result->getBatteryValue();
                    $device->setQoe($v);
                }
                break;
        }

        return $result;
    }

    function getTitle(): string
    {
        return '9位电子烟售货机蓝牙协议';
    }
}