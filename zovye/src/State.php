<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class State
{
    const OK = 0;
    const FAIL = 1;
    const ERROR = -1;
    const ERROR_LOCK_FAILED = 999;

    protected static $unknown = '未知';

    protected static $title = [
        self::OK => '成功',
        self::FAIL => '失败',
        self::ERROR => '错误',
    ];

    public static function has($state): bool
    {
        return array_key_exists($state, static::$title);
    }

    public static function desc($state): string
    {
        if (array_key_exists($state, static::$title)) {
            return static::$title[$state];
        }

        return static::$unknown;
    }
}
