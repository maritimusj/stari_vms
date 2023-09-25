<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\hmb;

use zovye\contract\bluetooth\ICmd;
use zovye\contract\bluetooth\IResponse;

class response implements IResponse
{
    private $data;

    /**
     * @param $data
     */
    public function __construct($data)
    {
        if (!empty($data)) {
            $this->data = bin2hex(base64_decode($data));
        }
    }

    function getID(): int
    {
        return strlen($this->data);
    }

    function isOpenResult(): bool
    {
        // 长度为 6 回复为运行结果
        return $this->getID() == 6;
    }

    function isOpenResultOk(): bool
    {
        return $this->isOpenResult() && substr($this->data, -2) == 'a5';
    }

    function isOpenResultFail(): bool
    {
        return !$this->isOpenResultOk();
    }

    function getErrorCode()
    {
        return null;
    }

    function isReady(): bool
    {
        return $this->hasBatteryValue() && $this->getBatteryValue() > 1;
    }

    function hasBatteryValue(): bool
    {
        // 长度为4的回复为电压值
        return $this->getID() == 4;
    }

    function getBatteryValue(): int
    {
        // 6.1v电压主板返回值为009d,所以假定9d为100%电量, 2.0v电压返回值为003f，此时认定为电量0
        $v = intval((hexdec($this->data) - 0x3f) / (0x9d - 0x3f) * 100);

        return min(100, max(1, $v)); //电量0时返回一个"低电量"值
    }

    function getMessage(): string
    {
        switch ($this->getID()) {
            case 4:
                return '=> 当前电量（'.$this->getBatteryValue().'%）';
            case 6:
                if ($this->data == 'a5a5a5') {
                    return '=> 开始出货';
                } elseif ($this->data == 'a5a500') {
                    return '=> 设备正忙';
                } elseif ($this->data == 'a5a5ff') {
                    return '=> 出货完成';
                } else {
                    return '=> 未知消息';
                }
            default:
                return '=> 未知消息';
        }
    }

    function getSerial()
    {
        return null;
    }

    function getRawData()
    {
        return $this->data;
    }

    function getEncodeData()
    {
        return $this->data;
    }

    function getAttachedCMD(): ?ICmd
    {
        return null;
    }
}