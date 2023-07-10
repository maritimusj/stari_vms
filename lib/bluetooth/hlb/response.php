<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\hlb;

use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

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
        return $this->getID() == 2;
    }

    function isOpenResultOk(): bool
    {
        return $this->isOpenResult() && $this->data == '01';
    }

    function isOpenResultFail(): bool
    {
        return $this->isOpenResult() && $this->data == '00';
    }

    function getErrorCode()
    {
        return null;
    }

    function isReady(): bool
    {
        return $this->hasBatteryValue() && $this->getBatteryValue() > 0;
    }

    function hasBatteryValue(): bool
    {
        return $this->getID() == 4;
    }

    function getBatteryValue(): int
    {
        // 6.1v电压主板返回值为009d,所以假定9d为100%电量
        $v = intval(hexdec($this->data) / 0x9d * 100);
        return min(100, max(0, $v));
    }

    function getMessage(): string
    {
        switch ($this->getID()) {
            case 2: return '=> 开锁结果' . ($this->isOpenResultOk() ? '（成功）':'（失败）');
            case 4: return '=> 当前电量（' . $this->getBatteryValue() . '%）' ;
            case 6: return $this->data == 'a5a5a5' ? '=> 确认回复' : '=> 未知消息';
            case 8: return '=> 连接成功';
            default: return '=> 未知消息';
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