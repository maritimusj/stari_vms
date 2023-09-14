<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx9se;

use zovye\BlueToothProtocol;
use zovye\contract\bluetooth\IBlueToothProtocol;
use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;
use zovye\domain\Device;
use zovye\Log;

class protocol implements IBlueToothProtocol
{
    const MSG_LEN = 20;

    const CMD_SHAKE_HAND = 0x09;
    const CMD_QUERY = 0x02;
    const CMD_CONFIG = 0x03;
    const CMD_NOTIFY = 0x04;
    const CMD_TEST = 0x89;

    public static $strMsg = [
        self::CMD_SHAKE_HAND => [
            self::KEY_SHAKE => '<= 握手',
            self::KEY_VERIFY => '<= 校验',
        ],
        self::CMD_QUERY => [
            self::KEY_INFO => '<= 获取基本信息',
            self::KEY_BATTERY => '<= 获取电池电量',
            self::KEY_TIME => '<= 获取时间',
            self::KEY_LIGHTS_SCHEDULE => '<= 获取灯光计划',
        ],
        self::CMD_CONFIG => [
            self::KEY_LOCKER => '<= 开锁',
            self::KEY_TIMER => '<= 时间',
            self::KEY_LIGHTS => '<= 灯光',
        ],
        self::CMD_NOTIFY => [

        ],
        self::CMD_TEST => [

        ],
    ];

    //CMD_SHAKE_HAND 相关KEY
    const KEY_SHAKE = 0x01;
    const KEY_VERIFY = 0x02;

    //CMD_CONFIG 相关KEY
    const KEY_LOCKER = 0x12;
    const KEY_TIMER = 0x13;
    const KEY_LIGHTS = 0x14;

    const RESULT_LOCKER_FAIL = 0;
    const RESULT_LOCKER_WAIT = 1;
    const RESULT_LOCKER_SUCCESS = 2;
    const RESULT_LOCKER_FAIL_TIMEOUT = 3;
    const RESULT_LOCKER_FAIL_LOW_BATTERY = 4;

    //CMD_QUERY 相关KEY
    const KEY_INFO = 0x01;
    const KEY_BATTERY = 0x11;
    const KEY_TIME = 0x13;
    const KEY_LIGHTS_SCHEDULE = 0x14;

    function getTitle(): string
    {
        return '9位电子烟售货机蓝牙协议(wx9se v1.0)';
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

        //蓝牙连接成功，返回握手请求命令
        $cmd = new ShakeHandCMD($device_id, $data);

        Log::debug($device_id, [
            'data' => $data,
            'hex' => $cmd->getEncoded(IBlueToothProtocol::HEX),
        ]);

        return $cmd;
    }

    function initialize($device_id)
    {
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return null;
        }
    }

    function open($device_id, $data): ?ICmd
    {
        if (isset($data['locker'])) {
            return new OpenDeviceCMD($data['locker']);
        }

        return null;
    }

    function parseResponse($device_id, $data): ?IResponse
    {
        $device = Device::get($device_id, true);
        if (empty($device)) {
            return null;
        }

        $result = new response($device_id, $data);
        if (!$result->isValid()) {
            Log::error($device_id, [
                'data' => $data,
                'hex' => bin2hex(base64_decode($data)),
                'error' => '无效的回复！',
            ]);

            return null;
        }

        $cmd_code = $result->getID();
        $cmd_key = $result->getKey();
        switch ($cmd_code) {
            case self::CMD_SHAKE_HAND:
                if ($cmd_key == self::KEY_SHAKE) {
                    $mac = $device->getMAC();
                    $randomData = $device->settings('wx9se.random.data', []);
                    $data = $result->getPayloadData(2, 16);
                    if (Helper::verifyCRC16Data($mac, $randomData, $data)) {
                        //握手通过，返回APP检验请求
                        $crc = Helper::getCrc16Data($mac, $randomData, Helper::HIGH);
                        $result->setCmd(new AppVerifyCMD($crc));
                    }
                } elseif ($cmd_key == self::KEY_VERIFY) {
                    if ($result->getPayloadData(2, 1)) {
                        //APP检验通过，返回获取设备基本信息请求
                        //$result->setCmd(new BaseInfoCMD());
                    }
                }
                break;
            case self::CMD_QUERY:
                if ($cmd_key == self::KEY_INFO) {
                    $version = $result->getVersion();
                    $device->updateSettings('wx9se.ver', $version);
                    //返回设备电量信息的请求
                    $result->setCmd(new BatteryCMD());
                } elseif ($cmd_key == self::KEY_BATTERY) {
                    $v = $result->getBatteryValue();
                    $device->setQoe($v);
                }
                break;
            case self::CMD_CONFIG:
            case self::CMD_NOTIFY:
                if ($cmd_key == self::KEY_BATTERY) {
                    $v = $result->getBatteryValue();
                    $device->setQoe($v);
                } elseif ($cmd_key == self::KEY_LOCKER) {
                    //todo
                }
                break;
            default:
        }

        return $result;
    }
}