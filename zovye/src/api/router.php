<?php

namespace zovye\api;

use zovye\JSON;
use zovye\Util;
use zovye\We7;
use function zovye\is_error;

class router
{
    public static function exec($op, $map)
    { 
        $fn = $map[$op];
        $with_transaction = We7::starts_with($fn, '@');
        $fn = $with_transaction ? ltrim($fn, '@') : $fn;
        if (is_callable($fn)) {
            $result = $with_transaction ? Util::transactionDo($fn) : $fn();
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