<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class PlaceHolder
{
    public static function url($url, $params = [])
    {
        foreach ($params as $index => $o) {
            if ($o instanceof userModelObj) {
                $url = str_ireplace(is_string($index) ? "\{$index}" : '{user_uid}', $o->getOpenid(), $url);
            } elseif ($o instanceof deviceModelObj) {
                $url = str_ireplace(is_string($index) ? "\{$index}" : '{device_uid}', $o->getShadowId(), $url);
            } elseif ($o instanceof DateTime) {
                $url = str_ireplace(is_string($index) ? "\{$index}" : '{timestamp}', $o->getTimestamp(), $url);
            }
        }
        return preg_replace('/{.*?}/i', '', $url);
    }
}