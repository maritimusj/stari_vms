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
 * @method static mixed agent(string $key, $v = '', $update = false)
 * @method static mixed location(string $key, $v = '', $update = false)
 * @method static mixed balance(string $key, $v = '', $update = false)
 * @method static mixed device(string $key, $v = '', $update = false)
 * @method static mixed charging(string $key, $v = '', $update = false)
 * @method static mixed fueling(string $key, $v = '', $update = false)
 * @method static mixed app(string $key, $v = '', $update = false)
 * @method static mixed cztv(string $key, $v = '', $update = false)
 * @method static mixed douyin(string $key, $v = '', $update = false)
 * @method static mixed wxplatform(string $key, $v = '', $update = false)
 * @method static mixed notify(string $key, $v = '', $update = false)
 * @method static mixed donatePay(string $key, $v = '', $update = false)
 * @method static mixed GDCVMachine(string $key, $v = '', $update = false)
 * @method static mixed api(string $string, $bool = '', $update=false)
 * @method static mixed tk(string $string, $bool = '', $update=false)
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