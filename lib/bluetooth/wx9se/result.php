<?php

namespace bluetooth\wx9se;

use zovye\Contract\bluetooth\IResult;

class result implements IResult
{
    private $device_id;
    private $data;

    /**
     * @param $device_id
     * @param  $data
     */
    public function __construct($device_id, $data)
    {
        $this->device_id = $device_id;
        $this->data = base64_decode($data);
    }

    function isValid(): bool
    {
        return strlen($this->data) === protocol::MSG_LEN;
    }

    function isOpenResultOk(): bool
    {
        if ($this->getCmd() == protocol::CMD_CONFIG && $this->getKey() == protocol::KEY_LOCKER) {
            return $this->getPayloadData(0, 1) == protocol::RESULT_LOCKER_SUCCESS;
        }
        return false;
    }

    function isOpenResultFail(): bool
    {
        if ($this->getCmd() == protocol::CMD_CONFIG && $this->getKey() == protocol::KEY_LOCKER) {
            return $this->getPayloadData(0, 1) != protocol::RESULT_LOCKER_SUCCESS;
        }
        return false;
    }

    function isReady()
    {
        // TODO: Implement isReady() method.
    }

    function getBatteryValue(): int
    {
        if ($this->getCmd() == protocol::CMD_QUERY && $this->getKey() == protocol::KEY_BATTERY) {
            return $this->getPayloadData(6, 1);
        }
        return -1;
    }

    function getCode()
    {
        return $this->getCmd();
    }

    function getMessage(): string
    {
        $cmd = $this->getCmd();
        $key = $this->getKey();

        if ($cmd == protocol::CMD_SHAKE_HAND) {
            if ($key == protocol::KEY_SHAKE) {
                return '<= APP握手结果';
            } elseif ($key == protocol::KEY_VERIFY) {
                $result = $this->getPayloadData(0, 1);
                return $result ? '<= APP检验成功' : '<= APP检验失败';
            }
            return '<= 未知握手数据';
        } elseif ($cmd == protocol::CMD_CONFIG) {
            $result = $this->getPayloadData(0, 1);
            if ($key == protocol::KEY_LOCKER) {
                $prefix = '<= 开锁结果：';
                switch ($result) {
                    case 0:
                        return $prefix . '失败';
                    case 1:
                        return $prefix . '成功，等待开锁';
                    case 2:
                        return $prefix . '成功';
                    case 3:
                        return $prefix . '失败，超时';
                    case 4:
                        return $prefix . '失败，电量低';
                    default:
                        return $prefix . '未知';
                }
            } elseif ($key == protocol::KEY_TIMER) {
                return $result ? '<= 设置时间：成功' : '<= 设置时间：失败';
            } elseif ($key == protocol::KEY_LIGHTS) {
                return $result ? '<= 设置开关灯时间：成功' : '<= 设置时间：失败';
            }
            return '<= 未知设置结果';

        } elseif ($cmd == protocol::CMD_QUERY) {
            if ($key == protocol::KEY_INFO) {
                return '<= 设备基本信息';
            } elseif ($key == protocol::KEY_BATTERY) {
                $v = $this->getBatteryValue();
                return '<= 设备电量：' . ($v != -1 ? $v . '%' : '<未知>');
            } elseif ($key == protocol::KEY_TIME) {

            } elseif ($key == protocol::KEY_LIGHTS_SCHEDULE) {

            }
            return '<= 未知请求结果';

        } elseif ($cmd == protocol::CMD_NOTIFY) {

        } elseif ($cmd = protocol::CMD_TEST) {

        }
        return '<= 未知数据';
    }

    function getDeviceID()
    {
        return $this->device_id;
    }

    function getSerial(): string
    {
        return '';
    }

    function getRawData()
    {
        return $this->data;
    }

    function getCmd()
    {
        $v = unpack('C', $this->data);
        return $v ? $v[1] : 0;
    }

    function getKey()
    {
        $v = unpack('C', $this->data, 1);
        return $v ? $v[1] : 0;
    }

    function getVersion(): string
    {
        $data = $this->getPayloadData(6, 4);
        return "$data[0]$data[1].$data[2].$data[3]";
    }

    public function getPayloadData($pos = 0, $len = 0)
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