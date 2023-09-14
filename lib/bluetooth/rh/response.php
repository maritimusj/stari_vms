<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\rh;

use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;

class response implements IResponse
{
    private $device_id;
    private $data;

    public function __construct($device_id, $data)
    {
        $this->device_id = $device_id;
        $this->data = base64_decode($data);
    }

    public function getID(): string
    {
        if (strlen($this->data) == 2) {
            return protocol::VOLTAGE;
        }

        if (strlen($this->data) == 6) {
            return protocol::SECRET;
        }

        if (substr($this->data, 0, 1) == '{' && substr($this->data, -1) == '}') {
            return protocol::RESULT;
        }

        return protocol::UNKNOWN;
    }

    public function isOpenResult(): bool
    {
        return $this->getID() == protocol::RESULT;
    }

    public function isOpenResultOk(): bool
    {
        if ($this->getID() == protocol::RESULT) {
            $result = json_decode($this->data, true);
            return is_array($result) && $result['Res'] === 0;
        }

        return false;
    }

    public function isOpenResultFail(): bool
    {
        if ($this->getID() == protocol::RESULT) {
            $result = json_decode($this->data, true);
            return is_array($result) && $result['Res'] !== 0;
        }

        return false;
    }

    public function getErrorCode(): int
    {
        if ($this->isOpenResult()) {
            return $this->isOpenResultOk() ? 0 : -1;
        }

        return 0;
    }

    public function isReady(): bool
    {
        return Helper::isReady($this->device_id);
    }

    public function hasBatteryValue(): bool
    {
        return $this->getID() == protocol::VOLTAGE;
    }

    public function getBatteryValue()
    {
        if ($this->getID() == protocol::VOLTAGE) {
            $v = unpack('n', $this->data);
            if ($v) {
                return min(100, max(0, round($v[1] / 450) * 100));
            }

            return 1;
        }

        return -1;
    }

    public function getMessage(): string
    {
        switch ($this->getID()) {
            case protocol::VOLTAGE:
                return '=> 电压电量：'.$this->getBatteryValue().'%';
            case protocol::SECRET:
                return '=> 随机密钥：'.$this->getEncodeData();
            case protocol::RESULT:
                return $this->isOpenResultOk() ? '=> 出货成功' : '=> 出货失败';
            case protocol::UNKNOWN:
                return '=> 其它消息';
        }

        return '=> 未知消息';
    }

    public function getSerial(): string
    {
        return '';
    }

    public function getRawData(): string
    {
        return $this->data;
    }

    public function getEncodeData(): string
    {
        return base64_encode($this->data);
    }

    public function getAttachedCMD(): ?ICmd
    {
        return null;
    }
}