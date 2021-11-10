<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class ZovyeException extends RuntimeException
{
    private $device = null;
    private $user = null;

    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function setDevice(deviceModelObj $device = null)
    {
        $this->device = $device;
    }

    public function getDevice(): ?deviceModelObj
    {
        return $this->device;
    }

    public function setUser(userModelObj $user = null)
    {
        $this->user = $user;
    }

    public function getUser(): ?userModelObj
    {
        return $this->user;
    }

    public static function throw($message, $code = 0)
    {
        throw new ZovyeException($message, $code);
    }

    public static function throwWith($message, $code = 0, ...$obj)
    {
        $e = new ZovyeException($message, $code);

        foreach ($obj as $o) {
            if ($o instanceof deviceModelObj) {
                $e->setDevice($o);
            } elseif ($o instanceof userModelObj) {
                $e->setUser($o);
            }
        }

        throw $e;
    }
}