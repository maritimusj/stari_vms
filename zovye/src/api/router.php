<?php

namespace zovye\api;

use zovye\JSON;
use function zovye\is_error;

class router
{
    public static function exec($op, $map)
    { 
        $fn = $map[$op];
        if (is_callable($fn)) {
            $result = $fn();
            if (is_error($result)) {
                JSON::fail($result);
            } else {
                if (is_string($result)) {
                    JSON::success(['msg' => $result]);
                }
                JSON::success($result);
            }
        } else {
            JSON::fail('不正确的调用？' . strval($op));
        }
    }
}