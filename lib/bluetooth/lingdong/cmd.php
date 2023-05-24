<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\lingdong;

use zovye\Cache;
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

    function getMessage()
    {
        switch ($this->id) {
            case 0x01:
                return '=> 连接握手';
            case 0x02:
                return '=> 请求开锁';
            case 0x05:
                return '=> 获取电量';
        }

        return '未知命令';
    }

    function nextSEQ()
    {
        $uid = "SEQ:$this->device_id";
        $seq = Cache::fetch($uid, function () {
            return 0;
        });
        if ($seq > 255) {
            $seq = 0;
        } else {
            $seq++;
        }
        Cache::set($uid, $seq);

        return $seq;
    }

    function crc($data) {
        $v = 0x00;
        for ($i = 0; $i < strlen($data); $i++) {
            $c = unpack('c', $data[$i]);
            $v = $v ^ $c[1];
        }

        return $v;
    }

    function encode()
    {
        $data = pack(
            'C*',
            self::HEADER,
            $this->nextSEQ(),
            $this->device_id,
            $this->id,
            ...$this->data
        );
        return $data . pack('C*', $this->crc($data));
    }

    function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}