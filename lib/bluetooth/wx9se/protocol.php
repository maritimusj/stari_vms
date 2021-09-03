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

    const LOW = 0;
    const HIGH = 1;

    const CONNECTED = 'connected';


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
        if (empty($device)) {
            return null;
        }

        $data = [];

        //16位非零随机数
        for ($i = 0; $i < 16; $i++) {
            $data[] = rand(1, 127);
        }
        //保存握手随机数值
        $device->updateSettings('wx9se.random.data', $data);
        $device->updateSettings('wx9se.state', self::CONNECTED);

        $data[] = 0;
        $data[] = 0;

        //蓝牙连接成功，返回握手请求命令
        return new ShakeHandCMD($device_id, $data);
    }

    function initialize($device_id)
    {
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return null;
        }

        $state = $device->settings('wx9se.state');
        switch ($state) {
            case self::CONNECTED:
                $data = $device->settings('wx9se.random.data', []);
                return new AppVerifyCMD($data);
        }
    }

    /**
     * @inheritDoc
     */
    function open($device_id, $data): ?ICmd
    {
        // TODO: Implement open() method.
        return null;
    }


    function getCrc16Data($mac, array $code, $lowOrHigh): array
    {
        $crc16 = new CRC16XModem();

        $mask = $lowOrHigh == self::LOW ? 0xFF00 : 0x00FF;

        $result = [];
        foreach ($code as $c) {

            $crc16->update($c);
            $crc16->update($mac);
            $v = $crc16->finish();

            $result[] = $v & $mask;

            $crc16->reset();
        }

        return $result;
    }

    function verifyCRC16Data($mac, array $code, array $crc16): bool
    {
        $data = $this->getCrc16Data($mac, $code, self::LOW);
        if (count($data) != count($crc16)) {
            return false;
        }
        foreach ($data as $index => $v) {
            if ($v != $crc16[$index]) {
                return false;
            }
        }
        return true;
    }


    /**
     * @inheritDoc
     */
    function parseMessage($device_id, $data):?IResult
    {
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return null;
        }

        $result = new result($device_id, $data);
        if (!$result->isValid()) {
            return null;
        }

        $code = $result->getCode();
        $key = $result->getKey();

        switch ($code) {
            case self::CMD_SHAKE_HAND:
                if ($key == self::KEY_SHAKE) {
                    $mac = $device->getMAC();
                    $randomData = $device->settings('wx9se.random.data', []);
                    $data = $result->getPayloadData();
                    if (!$this->verifyCRC16Data($mac, $randomData, $data)) {
                        return null;
                    }
                    //握手通过，返回APP检验请求
                    $crc  = $this->getCrc16Data($mac, $randomData, self::HIGH);
                    $result->setCmd(new AppVerifyCMD($crc));
                } elseif ($key == self::KEY_VERIFY) {
                    if (!$result->getPayloadData(0, 1)) {
                        return null;
                    }
                    //APP检验通过，返回获取设备基本信息请求
                    $result->setCmd(new BaseInfoCMD());
                }
                break;
            case self::CMD_CONFIG:
                break;
            case self::CMD_QUERY:
                if ($key == self::KEY_INFO) {
                    $version = $result->getVersion();
                    $device->updateSettings('wx9se.ver', $version);
                    //返回设备电量信息的请求
                    $result->setCmd(new BatteryCMD());
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