<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Contract\bluetooth\ICmd;
use zovye\Device;

class cmd implements ICmd
{
    const HEADER = [0xff, 0x55, 0x12, 0x01];

    private $device_id;

    private $id;

    private $data;

    /**
     * @param $device_id
     * @param $id
     * @param $data
     */
    public function __construct($device_id, $id, $data)
    {
        $this->device_id = $device_id;
        $this->id = $id;
        $this->data = $data;
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getID()
    {
        return $this->id;
    }

    function getData()
    {
        return $this->data;
    }

    function getRaw()
    {
        return $this->data;
    }

    function getMessage(): string
    {
        switch ($this->id) {
            case 0x01:
                return '<= 连接握手';
            case 0x02:
                return '<= 请求开锁';
            case 0x05:
                return '<= 获取电量';
        }

        return '<= 未知命令';
    }

    static function resetSEQ($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $device->updateSettings('lingdong.seq', 0);
        }
    }

    static function nextSEQ($device_id)
    {
        $device = Device::get($device_id, true);
        if ($device) {
            $seq = $device->settings('lingdong.seq', 0);
            if ($seq > 255) {
                $seq = 0;
            } else {
                $seq ++;
            }

            $device->updateSettings('lingdong.seq', $seq);
            return $seq;
        }

        return rand(0, 256);
    }

    function crc($data): int
    {
        $crc = 0;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x80) {
                    $crc = ($crc << 1) ^ 0x07;
                } else {
                    $crc <<= 1;
                }
            }
        }

        return $crc & 0xFF;
    }

    function encode(): string
    {
        $msg = pack('c*', ...self::HEADER).
            pack('c', self::nextSEQ($this->device_id)).
            pack('H*', $this->device_id).
            pack('c',  $this->id).
            pack('c*', ...$this->data);
        return $msg.pack('c*', $this->crc($msg));
    }

    function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}