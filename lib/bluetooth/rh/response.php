<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\rh;

use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class response implements IResponse
{
    private $device_id;
    private $data;

    public function __construct($device_id, $data)
    {
        $this->device_id = $device_id;
        $this->data = $data;
    }

    function getID(): string
    {
        if (count($this->data) == 2) {
            return protocol::VOLTAGE;
        }

        if (count($this->data) == 6) {
            return protocol::VOLTAGE;
        }

        return protocol::RESULT;
    }

    function isOpenResultOk(): bool
    {
        if ($this->getID() == protocol::RESULT) {
            $result = json_decode($this->data, true);
            return $result['Res'] === 1;
        }

        return false;
    }

    function isOpenResultFail(): bool
    {
        if ($this->getID() == protocol::RESULT) {
            $result = json_decode($this->data, true);
            return $result['Res'] === 0;
        }

        return false;
    }

    function getErrorCode(): int
    {
        if ($this->isOpenResultOk()) {
            return 0;
        }
        return -1;
    }

    function isReady(): bool
    {
        return protocol::isReady($this->device_id);
    }

    function getBatteryValue()
    {
        if ($this->getID() == protocol::VOLTAGE) {
            $v = unpack('C', $this->data);
            if ($v) {
                return min(100, max(0, $v[0] / 450 * 100));
            }
            return 1;
        }
        return -1;
    }

    function getMessage(): string
    {
        switch ($this->getID()) {
            case protocol::VOLTAGE:
                return '<= 电压电量';
            case protocol::SECRET:
                return '<= 随机密钥';
            case protocol::RESULT:
                return $this->isOpenResultOk() ? '出货成功' : '出货失败';
        }
        return '<= 未知消息';
    }

    function getSerial(): string
    {
        return '';
    }

    function getRawData()
    {
        return $this->data;
    }

    function getCmd(): ?ICmd
    {
        return null;
    }
}