<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wxu;

use zovye\Contract\bluetooth\ICmd;

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

    public function getDeviceID()
    {
        return $this->device_id;
    }

    public function getID()
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getRaw()
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        switch ($this->id) {
            case 0x01:
                return '<= 连接握手';
            case 0x02:
                return '<= 请求开锁';
            case 0x04:
                return '<= 开锁测试';
        }

        return '<= 未知命令';
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
            pack('c', Helper::nextSEQ($this->device_id)).
            pack('H*', $this->device_id).
            pack('c',  $this->id).
            pack('c*', ...$this->data);
        return $msg.pack('c*', $this->crc($msg));
    }

    public function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}