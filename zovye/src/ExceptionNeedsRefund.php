<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;

class ExceptionNeedsRefund extends RuntimeException
{
    private $device = null;
    private $num = 0;

    /**
     * @return deviceModelObj
     */
    public function getDevice(): deviceModelObj
    {
        return $this->device;
    }

    /**
     * @param deviceModelObj $device
     */
    public function setDevice(deviceModelObj $device)
    {
        $this->device = $device;
    }

    public function setNum(int $num)
    {
        $this->num = $num;
    }

    public function getNum(): int
    {
        return $this->num;
    }

    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function throw($message, $code = 0)
    {
        throw new ExceptionNeedsRefund($message, $code);
    }

    public static function throwWith($device, $message, $code = 0)
    {
        $e = new ExceptionNeedsRefund($message, $code);

        $e->setDevice($device);
        $e->setNum(0);

        throw $e;
    }

    public static function throwWithN($device, $num, $message, $code = 0)
    {
        $e = new ExceptionNeedsRefund($message, $code);

        $e->setDevice($device);
        $e->setNum($num);

        throw $e;
    }

}