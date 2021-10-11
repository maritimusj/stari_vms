<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace bluetooth\wx9se;

use zovye\Contract\bluetooth\ICmd;

class cmd implements ICmd
{
    protected $device_id;
    protected $id;
    protected $key;
    protected $data;

    /**
     * @param $device_id
     * @param $id
     * @param $key
     * @param mixed $data
     */
    public function __construct($device_id, $id, $key, $data = '')
    {
        $this->device_id = $device_id;
        $this->id = $id;
        $this->key = $key;
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
        return $this->encode();
    }

    function encode()
    {
        return pack('C*', $this->id, $this->key, ...$this->data);
    }

    public function getPayloadData($pos = 0, $len = 0)
    {
        if ($pos == 0 && $len == 0) {
            return $this->data;
        }
        if ($len == 1) {
            return $this->data[$pos] ?? 0;
        }
        return array_slice($this->data, $pos, $len);
    }

    function getMessage(): string
    {
        $msg = protocol::$strMsg[$this->id][$this->key] ?? '<未知>';
        if ($this->id == protocol::CMD_CONFIG && $this->key == protocol::KEY_LOCKER) {
            $lock_id = $this->getPayloadData(0, 1);
            $msg .= ($lock_id > 0 ? "($lock_id)" : '(复位)');
        }
        return $msg;
    }

    function getEncoded($fn = null)
    {
        return is_callable($fn) ? call_user_func($fn, $this->encode()) : base64_encode($this->encode());
    }
}