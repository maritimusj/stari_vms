<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wxu;

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

    public function getID()
    {
        return $this->getPayloadData(11, 1);
    }

    public function isOpenResult(): bool
    {
        return $this->getID() == 0x02;
    }

    public function isOpenResultOk(): bool
    {
        return $this->getID() == 0x02 && ($this->getResultValue() == 0x03 || $this->getResultValue() == 0x01);
    }

    public function isOpenResultFail(): bool
    {
        return $this->getID() == 0x02 && $this->getResultValue() != 0x03;
    }

    public function isReady(): bool
    {
        return $this->getID() == 0x06 && $this->getBatteryValue() > 0;
    }

    public function hasBatteryValue(): bool
    {
        return $this->getID() == 0x06;
    }

    public function getBatteryValue(): int
    {
        // 正常电压范围 0x42 ~ 0x62
        return min(100, max(0, ($this->getResultValue() - 0x42) / 20 * 100));
    }

    public function getErrorCode(): int
    {
        if ($this->isOpenResult() && $this->isOpenResultFail()) {
            return -1;
        }
        return 0;
    }

    public function getMessage(): string
    {
        $id = $this->getID();
        $res = $this->getResultValue();

        switch ($id) {
            case 0x01:
                if ($res) {
                    return '=> 握手成功';
                }
                return '=> 握手失败';
            case 0x02:
                if ($res == 0x03) {
                    return '=> 开锁成功';
                }
                if ($res == 0x04) {
                    return '=> 开锁失败（电量低）';
                }
                if ($res == 0x05) {
                    return '=> 开锁失败（机器故障）';
                }
                return '=> 开锁失败';
            case 0x06:
                return "=> 当前电量：{$this->getBatteryValue()}%";
        }

        return '=> 未知消息';
    }

    public function getDeviceID()
    {
        return $this->device_id;
    }

    public function getSerial(): string
    {
        return  $this->getPayloadData(4, 1);
    }

    public function getRawData(): string
    {
        return $this->data;
    }

    function getEncodeData(): string
    {
        return bin2hex($this->data);
    }

    public function getAttachedCMD(): ?ICmd
    {
        if ($this->getID() == 0x01) {
            return new BatteryCmd($this->device_id);
        }
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