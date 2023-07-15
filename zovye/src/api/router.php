<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use zovye\JSON;

class router
{
    public static function exec($op, $map)
    {
        $fn = $map[$op];

        if (is_callable($fn)) {
            $result = call_user_func($fn);
            JSON::result($result);
        }

        JSON::fail('不正确的调用:'.$op);
    }
}