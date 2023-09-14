<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\base;

class ClassLoader
{
    static $alias = [
        'qrcode' => LIB_DIR.'qrcode'.DIRECTORY_SEPARATOR.'phpqrcode.php',
    ];

    public function library($name)
    {
        $e = self::$alias[$name];
        $files = is_array($e) ? $e : [$e];
        foreach ($files as $file) {
            require_once($file);
        }
    }
}