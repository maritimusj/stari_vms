<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use Exception;
use zovye\JSON;

class router
{
    public static function exec($op, $map)
    {
        $fn = $map[$op];

        if (is_callable($fn)) {
            try {
                $result = call_user_func($fn);
                JSON::result($result);
            } catch (Exception $e) {
                JSON::fail($e);
            }
        }

        JSON::fail('不正确的调用:'.$op);
    }
}