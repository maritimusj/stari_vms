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
            if ($v >= 0x10) {
                return max(0, min(100, ($v - 0x10) * 25));
            }
        }
        return -1;
    }

    function getCode()
    {
        return $this->device_id;
    }

    function getMessage()
    {
        $code = $this->getCode();
        $res = $this->getPayloadData(12, 1);

        switch ($code) {
            case 0x01:
                if ($res) {
                    return '<= 握手结果(成功)，电量：'. $this->getBatteryValue() . '%';
                }
                return '<= 握手结果(失败)';
            case 0x02:
                return '<= 开锁结果(' . $res == 0x03 ? '成功' : '失败' . ')';
            case 0x05:
                return '<= 电量：' . $this->getBatteryValue() . '%';
        }

        return '<= 未知消息';
    }

    function getDeviceID()
    {
        return $this->getPayloadData(5, 6);
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