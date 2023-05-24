<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\IResult;

class result implements IResult
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

    function isValid()
    {
        return strlen($this->data) == 18;
    }

    function isOpenResultOk()
    {
        return $this->getCode() == 0x03 && $this->getPayloadData(12, 1) == 0x01;
    }

    function isOpenResultFail()
    {
        return $this->getCode() == 0x03 && $this->getPayloadData(12, 1) == 0x00;
    }

    function isReady()
    {
        return $this->getBatteryValue() > 0;
    }

    function getBatteryValue()
    {
        if ($this->getCode() == 0x01) {
            $v = $this->getPayloadData(12, 1);
            if ($v > 0x10) {
                return max(0, min(100, ($v - 0x10) * 25));
            }
        }
        return -1;
    }

    function getCode()
    {
        return $this->getPayloadData(11, 1);
    }

    function getMessage()
    {
        $code = $this->getCode();

        switch ($code) {
            case 0x01:
                return '<= 握手结果';
            case 0x03:
                return '<= 开锁结果';
            case 0x04:
                return '<= 电量结果';
        }

        return '<= 未知消息';
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getSerial()
    {
        return '';
    }

    function getRawData()
    {
        return $this->data;
    }

    function getCmd()
    {
        return null;
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