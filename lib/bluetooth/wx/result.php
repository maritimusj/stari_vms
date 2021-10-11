<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace bluetooth\wx;

use zovye\Contract\bluetooth\IResult;

class result implements IResult
{
    const MSG_ID_OFFSET = 4;
    const DEVICE_ID_OFFSET = 5;
    const RESULT_OFFSET = 12;

    static $strMsg = [
        0 => '<= 出货超时',
        0x01 => '<= 电机到位',
        0x02 => '<= 握手成功',
        0x03 => '<= 开锁成功',
        0x04 => '<= 电量低',
        0x05 => '<= 设备故障',
        0x10 => '<= 电量值(低)',
        0x11 => '<= 电量值(1格)',
        0x12 => '<= 电量值(2格)',
        0x13 => '<= 电量值(3格)',
        0x14 => '<= 电量值(4格)',
        0x15 => '<= 电量值(5格)',
    ];

    private $data = '';

    public function __construct($data, $base64encoded = true)
    {
        if ($base64encoded) {
            $this->data = base64_decode($data);
        } else {
            $this->data = $data;
        }
    }

    public function isValid()
    {
        return strlen($this->data) > 12;
    }

    public function isReady()
    {
        return $this->getCode() == 0x02 || $this->getCode() >= 0x10;
    }

    public function getBatteryValue()
    {
        $v = $this->getCode();
        if ($v < 0x10) {
            return -1;
        }
        if ($v == 0x10) {
            //返回一个"比较低"的电量值，表示设备缺电
            return 1;
        }
        return min(100, ($v - 0x10)*25);
    }   

    public function isOpenResultOk()
    {
        return $this->getCode() == 0x01 || $this->getCode() == 0x03;
    }

    public function isOpenResultFail()
    {
        return $this->getCode() === 0 || $this->getCode() == 0x04 || $this->getCode() == 0x05;
    }

    public function getCode()
    {
        if ($this->isValid()) {
            $res = unpack('C', $this->data[self::RESULT_OFFSET]);
            if ($res) {
                return intval($res[1]);
            }
        }
        return 0;
    }

    public function getMessage()
    {
        $code = $this->getCode();
        if (isset(self::$strMsg[$code])) {
            return self::$strMsg[$code];
        }
        return 'unknown';
    }

    public function getDeviceID()
    {
        if ($this->isValid()) {
            return ltrim(bin2hex(substr($this->data, self::DEVICE_ID_OFFSET, 6)), '0');
        }
        return '';
    }

    public function getSerial()
    {
        if ($this->isValid()) {
            $res = unpack('C', $this->data[self::MSG_ID_OFFSET]);
            if ($res) {
                return $res[1];
            }
        }
        return 0;
    }

    public function getRawData()
    {
        return bin2hex($this->data);
    }

    function getCmd()
    {
        return null;
    }

    function getPayloadData()
    {

    }
}
