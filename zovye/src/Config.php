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