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
            case 0x06:
                return '<= 查询电量';
        }

        return sprintf('<= 未知命令: 0x%0x',  $this->id);
    }

    function crc($data): int
    {
        $v = 0x00;
        for ($i = 0; $i < strlen($data); $i++) {
            $c = unpack('c', $data[$i]);
            $v = $v ^ $c[1];
        }

        return $v;
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