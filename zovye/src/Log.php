<?php

namespace zovye;

class Log
{
    public static $level = L_ALL;

    private static $suffix = [
        L_ALL => '',
        L_DEBUG => 'debug',
        L_INFO => 'info',
        L_WARN => 'warn',
        L_ERROR => 'error',
        L_FATAL => 'fatal',
    ];

    public static function append($level, $title, $data = [])
    {
        if ($level >= self::$level) {
            Util::logToFile($title, $data, true, self::$suffix[$level] ?? '');
        }
    }

    public static function error($title, $data = [])
    {
        self::append(L_ERROR, $title, $data);
    }

    public static function fatal($title, $data = [])
    {
        self::append(L_FATAL, $title, $data);
        exit();
    }

    public static function warning($title, $data = [])
    {
        self::append(L_WARN, $title, $data);
    }

    public static function info($title, $data = [])
    {
        self::append(L_INFO, $title, $data);
    }

    public static function debug($title, $data = [])
    {
        self::append(L_DEBUG, $title, $data);
    }
}