<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx9se;

use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;

class response implements IResponse
{
    private $device_id;
    private $data;
    private $cmd;

    /**
     * @param $device_id
     * @param  $data
     */
    public function __construct($device_id, $data)
    {
        $this->device_id = $device_id;
        $this->data = base64_decode($data);
    }

    function getID()
    {
        $v = unpack('C', $this->data);
        return $v ? $v[1] : 0;
    }

    function setCmd(ICmd $cmd = null)
    {
        $this->cmd = $cmd;
    }

    function isValid(): bool
    {
        return strlen($this->data) === protocol::MSG_LEN;
    }

    function isOpenResult(): bool
    {
        return $this->getID() == protocol::CMD_CONFIG && $this->getKey() == protocol::KEY_LOCKER;
    }

    function isOpenResultOk(): bool
    {
        if ($this->getID() == protocol::CMD_CONFIG && $this->getKey() == protocol::KEY_LOCKER) {
            return $this->getPayloadData(2, 1) == protocol::RESULT_LOCKER_SUCCESS;
        }
        return false;
    }

    function isOpenResultFail(): bool
    {
        if ($this->getID() == protocol::CMD_CONFIG && $this->getKey() == protocol::KEY_LOCKER) {
            $v = $this->getPayloadData(2, 1);
            return $v != protocol::RESULT_LOCKER_SUCCESS && $v != protocol::RESULT_LOCKER_WAIT;
        }
        return false;
    }

    function isReady(): bool
    {
        return $this->getBatteryValue() > 0;
    }

    function hasBatteryValue(): bool
    {
        return $this->getBatteryValue() == -1;
    }

    function getBatteryValue(): int
    {
        if (($this->getID() == protocol::CMD_QUERY || $this->getID() == protocol::CMD_NOTIFY)
            && $this->getKey() == protocol::KEY_BATTERY) {
            return $this->getPayloadData(7, 1) * 20;
        }
        return -1;
    }

    function getErrorCode(): int
    {
        if ($this->isOpenResultFail()) {
            return -1;
        }
        return 0;
    }

    function getMessage(): string
    {
        $cmd_code = $this->getID();
        $cmd_key = $this->getKey();

        if ($cmd_code == protocol::CMD_SHAKE_HAND) {
            if ($cmd_key == protocol::KEY_SHAKE) {
                return '<= APP握手结果';
            } elseif ($cmd_key == protocol::KEY_VERIFY) {
                $result = $this->getPayloadData(2, 1);
                return $result ? '<= APP检验成功' : '<= APP检验失败';
            }
            return '<= 未知握手数据';
        } elseif ($cmd_code == protocol::CMD_CONFIG) {
            $result = $this->getPayloadData(2, 1);
            if ($cmd_key == protocol::KEY_LOCKER) {
                $prefix = '<= 开锁结果：';
                switch ($result) {
                    case protocol::RESULT_LOCKER_FAIL:
                        return $prefix . '失败';
                    case protocol::RESULT_LOCKER_WAIT:
                        return $prefix . '成功，等待开锁';
                    case protocol::RESULT_LOCKER_SUCCESS:
                        return $prefix . '成功';
                    case protocol::RESULT_LOCKER_FAIL_TIMEOUT:
                        return $prefix . '失败，超时';
                    case protocol::RESULT_LOCKER_FAIL_LOW_BATTERY:
                        return $prefix . '失败，电量低';
                    default:
                        return $prefix . '未知';
                }
            } elseif ($cmd_key == protocol::KEY_TIMER) {
                return $result ? '<= 设置时间：成功' : '<= 设置时间：失败';
            } elseif ($cmd_key == protocol::KEY_LIGHTS) {
                return $result ? '<= 设置开关灯时间：成功' : '<= 设置时间：失败';
            }
            return '<= 未知设置结果';

        } elseif ($cmd_code == protocol::CMD_QUERY || $cmd_code == protocol::CMD_NOTIFY) {
            if ($cmd_key == protocol::KEY_INFO) {
                return '<= 设备基本信息';
            } elseif ($cmd_key == protocol::KEY_BATTERY) {
                $v = $this->getBatteryValue();
                return '<= 设备电量：' . ($v != -1 ? $v . '%' : '<未知>');
            } elseif ($cmd_key == protocol::KEY_TIME) {
                //todo
            } elseif ($cmd_key == protocol::KEY_LIGHTS_SCHEDULE) {
                //todo
            }
            return '<= 未知请求结果';

        } elseif ($cmd_code = protocol::CMD_TEST) {
            //todo
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

    function getAttachedCMD(): ?ICmd
    {
        return $this->cmd;
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