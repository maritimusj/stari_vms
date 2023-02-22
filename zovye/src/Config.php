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
 * @method static function agent(string $key, $v = '', $update = false)
 * @method static function balance(string $key, $v = '', $update = false)
 * @method static function device(string $key, $v = '', $update = false)
 * @method static function charging(string $key, $v = '', $update = false)
 * @method static function fueling(string $key, $v = '', $update = false)
 * @method static function app(string $key, $v = '', $update = false)
 * @method static function cztv(string $key, $v = '', $update = false)
 * @method static function douyin(string $key, $v = '', $update = false)
 * @method static function wxplatform(string $key, $v = '', $update = false)
 * @method static function notify(string $key, $v = '', $update = false)
 * @method static function donatePay(string $key, $v = '', $update = false)
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