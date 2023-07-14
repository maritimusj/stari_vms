<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use zovye\api\wx\common;
use zovye\CacheUtil;
use zovye\DBUtil;
use zovye\JSON;
use zovye\We7;

class router
{
    public static function exec($op, $map)
    {
        $fn = $map[$op];

        if (is_callable($fn)) {
            $result = $fn();
        } else {
            if (We7::starts_with($fn, '@')) {
                $fn = ltrim($fn, '@');
                if (is_callable($fn)) {
                    $result = DBUtil::transactionDo($fn);
                }
            } elseif (We7::starts_with($fn, '*')) {
                $fn = ltrim($fn, '*');
                if (is_callable($fn)) {
                    $result = CacheUtil::cachedCall(6, $fn, common::getToken());
                }
            }
        }

        if (isset($result)) {
            JSON::result($result);
        }

        JSON::fail('不正确的调用:' . $op);
    }
}