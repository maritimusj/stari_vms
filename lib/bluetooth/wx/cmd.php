<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx;

use zovye\Contract\bluetooth\ICmd;

class cmd implements ICmd
{
    const HEADER_LEN = 12;

    const CMD_SHAKE_HAND = 0x01;
    const CMD_RUN = 0x02;

    static $strMsg = [
        self::CMD_SHAKE_HAND => '=> 握手请求',
        self::CMD_RUN => '=> 出货请求',
    ];

    private $device_id;
    private $id;
    private $data;
    private $msgID;

    function crc($data): int
    {
        $v = 0x00;
        for ($i = 0; $i < strlen($data); $i++) {
            $c = unpack('c', $data[$i]);
            $v = $v ^ $c[1];
        }

        return $v;
    }

    /**
     * cmd.
     * @param $device_id
     * @param $id
     * @param $data
     */
    public function __construct($device_id, $id, $data)
    {
        $this->device_id = $device_id;
        $this->id = $id;
        $this->data = $data;
        $this->msgID = rand(1, 255);
    }

    /**
     * @return mixed
     */
    public function getDeviceID()
    {
        return $this->device_id;
    }

    /**
     * @return mixed
     */
    public function getID()
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->data;
    }

    function encodeDeviceID($device_id)
    {
        return pack('H*', str_pad($device_id, 12, '0', STR_PAD_LEFT));
    }

    function encode(): string
    {
        $customData = '';
        foreach ($this->data as $c) {
            $customData .= pack('c', $c);
        }
        $result = pack('c*', 0xff, 0x55)
            . pack('c*', strlen($customData) + self::HEADER_LEN + 1) //加1位crc长度
            . pack('c', 0x01)
            . pack('c', $this->msgID)
            . $this->encodeDeviceID($this->device_id)
            . pack('c', $this->id) . $customData;
        return $result . pack('c', $this->crc($result));
    }

    public function getRaw(): string
    {
        return $this->encode();
    }

    public function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }

    function getMessage(): string
    {
        return self::$strMsg[$this->id] ?? '<未知>';
    }
}