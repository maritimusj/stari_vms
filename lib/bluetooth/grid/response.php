<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\grid;

use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;
use zovye\Device;
use zovye\We7;

class response implements IResponse
{
    private $device_id;
    private $data;

    const RESPONSE = '+R:';
    const AUTH_SUCCESS = '+SUCCEED';
    const OPEN_SUCCESS = 'OK:';
    const ERR = 'ERR:';
    const VOLTAGE = 'V:';

    static $err_msg = [
        2000 => '已重启',
        2001 => '密钥错误',
        2002 => '未授权操作',
        2003 => '设备正忙',
    ];

    public function __construct($device_id, $data)
    {
        $this->device_id = $device_id;
        $this->data = base64_decode($data);
    }

    function getID(): string
    {
        return '';
    }

    function isOpenResult(): bool
    {
        return $this->isOpenResultOk() || $this->isOpenResultFail();
    }

    function isOpenResultOk(): bool
    {
        return We7::starts_with($this->data, self::OPEN_SUCCESS);
    }

    function isOpenResultFail(): bool
    {
        return We7::starts_with($this->data, self::ERR);
    }

    function getErrorCode(): int
    {
        if ($this->isOpenResultFail()) {
            return intval(trim(ltrim($this->data, self::ERR)));
        }
        return 0;
    }

    function isResponse(): bool
    {
        return We7::starts_with($this->data, self::RESPONSE);
    }

    function isAuthSuccess(): bool
    {
        return We7::starts_with($this->data, self::AUTH_SUCCESS);
    }

    function isOpenSuccess(): bool
    {
        return We7::starts_with($this->data, self::OPEN_SUCCESS);
    }

    function isVoltage(): bool
    {
        return We7::starts_with($this->data, self::VOLTAGE);
    }

    function isError(): bool
    {
        return We7::starts_with($this->data, self::ERR);
    }

    function getErrMsg(): string
    {
        $err = $this->getErrorCode();
        if ($err > 0) {
            $msg = self::$err_msg[$err];
            return $msg ?? '未知错误';
        }
        return '';
    }

    function getMessage(): string
    {
        if ($this->isResponse()) {
            return '<= 密钥回复';
        } elseif ($this->isAuthSuccess()) {
            return '<= 授权成功';
        } elseif ($this->isOpenSuccess()) {
            return '<= 开锁成功';
        } elseif ($this->isVoltage()) {
            return '<= 电池电量';
        } elseif ($this->isError()) {
            return '<= 发生错误：' . $this->getErrMsg();
        } elseif ($this->isOpenResultFail()) {
            return '<= ' . $this->getErrMsg();
        }
        return '<= 未知数据';
    }

    function getSerial(): string
    {
        return '';
    }

    function getRawData()
    {
        return $this->data;
    }

    function getEncodeData(): string
    {
        return $this->data;
    }

    function getAuthCmd(): ?ICmd
    {
        $device = Device::get($this->device_id, true);
        if ($device) {
            $mac = $device->getMAC();
            if ($mac) {
                $code = unpack('c', trim(ltrim($this->getRawData(), self::RESPONSE)));
                $result = (unpack('c', substr($mac, -1))[1]) ^ (unpack('c', substr($mac, -2, 1))[1]) ^ $code[1];
                $password = pack('c', $result);

                $device->updateSettings('grid.password', $password);
                return new Auth($device->getImei(), $password);
            }
        }
        return null;
    }

    function isReady(): bool
    {
        return $this->isAuthSuccess();
    }

    function hasBatteryValue(): bool
    {
        return false;
    }

    public function getBatteryValue(): int
    {
        return -1;
    }

    function getAttachedCMD(): ?ICmd
    {
        if ($this->isResponse()) {
            return $this->getAuthCmd();
        }

        return null;
    }
}