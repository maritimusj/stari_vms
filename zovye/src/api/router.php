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
use function zovye\toCamelCase;
use function zovye\toSnakeCase;

class router
{
    public static function load($w, $op)
    {
        $dir = ZOVYE_SRC . 'api' . DIRECTORY_SEPARATOR. $w . DIRECTORY_SEPARATOR;

        $op = toCamelCase($op);
        $file = $dir.$op.'.php';
        if (is_file($file)) {
            return require $file;
        }

        $op = toSnakeCase($op);
        $file = $dir.$op.'.php';
        if (is_file($file)) {
            return require $file;
        }

        return null;
    }

    public static function exec($op, $map)
    {
        $fn = $map[$op];

        if (is_callable($fn)) {
            $result = $fn();
        } else {
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
            }
        }

        if (isset($result)) {
            JSON::result($result);
        }

        JSON::fail('不正确的调用:' . $op);
    }
}