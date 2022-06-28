<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTimeInterface;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class PlaceHolder
{
    public static function replace($text, $params = [])
    {
        foreach ($params as $index => $o) {
            if ($o instanceof userModelObj) {
                $text = str_ireplace(is_string($index) ? '{'.$index.'}' : '{user_uid}', $o->getOpenid(), $text);
            } elseif ($o instanceof deviceModelObj) {
                $text = str_ireplace(is_string($index) ? '{'.$index.'}' : '{device_uid}', $o->getShadowId(), $text);
                $text = str_ireplace(is_string($index) ? '{'.$index.'}' : '{device_imei}', $o->getImei(), $text);
            } elseif ($o instanceof DateTimeInterface) {
                $text = str_ireplace(is_string($index) ? '{'.$index.'}' : '{timestamp}', $o->getTimestamp(), $text);
            } elseif (is_string($index) && is_scalar($o)) {
                $text = str_ireplace('{'.$index.'}', strval($o), $text);
            }
        }
        return preg_replace('/{.*?}/i', '', $text);
    }
}