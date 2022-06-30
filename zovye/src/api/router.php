<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use zovye\api\wx\common;
use zovye\JSON;
use zovye\Util;
use zovye\We7;

class router
{
    public static function exec($op, $map)
    {
        $fn = $map[$op];
        if (We7::starts_with($fn, '@')) {
            $fn = ltrim($fn, '@');
            if (is_callable($fn)) {
                $result = Util::transactionDo($fn);
            }
        } elseif (We7::starts_with($fn, '*')) {
            $fn = ltrim($fn, '*');
            if (is_callable($fn)) {
                $result = Util::cachedCall(6, $fn, common::getToken());
            }
        } else {
            if (is_callable($fn)) {
                $result = $fn();
            }
        }

        if (isset($result)) {
            JSON::result($result);
        }

        JSON::fail('不正确的调用:' . $op);
    }
}