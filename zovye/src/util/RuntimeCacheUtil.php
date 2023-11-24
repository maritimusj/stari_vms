<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\util;

class RuntimeCacheUtil
{
    public static function write($name, $var)
    {
        ob_start();
        var_export($var);
        $str = ob_get_clean();
        file_put_contents(RUNTIME_DIR.$name.'.php', $str);
    }

    public static function load($name)
    {
        return require_once RUNTIME_DIR.$name.'.php';
    }
}