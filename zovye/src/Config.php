<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

/**
 * 全局配置越来越大，这个类主要用来独立部分配置
 * Class Config
 * @package zovye
 * @method static agent(string $key, $v = '', $update = false)
 * @method static balance(string $key, $v = '', $update = false)
 * @method static device(string $key, $v = '', $update = false)
 * @method static charging(string $key, $v = '', $update = false)
 * @method static fueling(string $key, $v = '', $update = false)
 * @method static app(string $key, $v = '', $update = false)
 * @method static cztv(string $key, $v = '', $update = false)
 * @method static douyin(string $key, $v = '', $update = false)
 * @method static wxplatform(string $key, $v = '', $update = false)
 */
class Config
{
    public static function __callStatic($name, $arguments)
    {
        $key = strval($arguments[0]);
        $v = $arguments[1];
        $update = boolval($arguments[2]);

        if ($update) {
            return updateGlobalConfig($name, $key, $v);
        }

        return globalConfig($name, $key, $v);
    }
}