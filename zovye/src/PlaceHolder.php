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
    public static function replace($url, $params = [])
    {
        foreach ($params as $index => $o) {
            if ($o instanceof userModelObj) {
                $url = str_ireplace(is_string($index) ? '{'. $index . '}' : '{user_uid}', $o->getOpenid(), $url);
            } elseif ($o instanceof deviceModelObj) {
                $url = str_ireplace(is_string($index) ? '{'. $index . '}' : '{device_uid}', $o->getShadowId(), $url);
                $url = str_ireplace(is_string($index) ? '{'. $index . '}' : '{device_imei}', $o->getImei(), $url);
            } elseif ($o instanceof DateTimeInterface) {
                $url = str_ireplace(is_string($index) ? '{'. $index . '}' : '{timestamp}', $o->getTimestamp(), $url);
            } elseif (is_string($index) && is_string($o)) {
                $url = str_ireplace('{'. $index . '}', $o, $url);
            }
        }
        return preg_replace('/{.*?}/i', '', $url);
    }
}