<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class response implements IResponse
{
    private $device_id;
    private $data;
    /**
     * @param $device_id
     * @param $data
     */
    public function __construct($device_id, $data)
    {
        $this->device_id = $device_id;
        $this->data = base64_decode($data);
    }

    function getID()
    {
        return $this->getPayloadData(4, 1);
    }

    function isOpenResultOk(): bool
    {
        return $this->getID() == 0x03 && $this->getResultValue() == 0x01;
    }

    function isOpenResultFail(): bool
    {
        return $this->getID() == 0x03 && $this->getResultValue() == 0x00;
    }

    function isReady(): bool
    {
        return $this->getResultValue() == 0x01 || $this->getResultValue() > 0x10;
    }

    function getBatteryValue()
    {
        if ($this->getID() == 0x01) {
            $v = $this->getResultValue();
            if ($v > 0x10) {
                return max(0, min(100, ($v - 0x10) * 25));
            }
            return 1;
        }
        return -1;
    }

    function getErrorCode(): int
    {
        if ($this->isOpenResultFail()) {
            return -1;
        }
        return 0;
    }

    function getMessage(): string
    {
        $id = $this->getID();
        $res = $this->getResultValue();

        switch ($id) {
            case 0x01:
                if ($res) {
                    return '=> 握手结果(成功)，电量：'. $this->getBatteryValue() . '%';
                }
                return '=> 握手结果(失败)';
            case 0x02:
                return '=> 开锁结果(' . $res == 0x03 ? '成功' : '失败' . ')';
            case 0x05:
                return '=> 电量：' . $this->getBatteryValue() . '%';
        }

        return '=> 未知消息';
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getSerial(): string
    {
        return  $this->getPayloadData(4, 1);
    }

    function getRawData()
    {
        return $this->data;
    }

    function getCmd(): ?ICmd
    {
        return null;
    }

    function getResultValue()
    {
        return $this->getPayloadData(12, 1);
    }

    function getPayloadData($pos = 0, $len = 0)
    {
        static $data = null;
        if (!isset($data)) {
            $data = array_values(unpack('C*', $this->data));
        }
        if ($pos == 0 && $len == 0) {
            return $data;
        }
        if ($len == 1) {
            return $data[$pos] ?? 0;
        }
        return array_slice($data, $pos, $len);
    }

}