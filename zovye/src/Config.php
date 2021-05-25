<?php

namespace zovye;

/**
 * 全局配置越来越大，这个类主要用来独立部分配置
 * Class Config
 * @package zovye
 */
class Config
{
    /**
     * 天猫拉新相关配置
     * @param $key
     * @param null $v
     * @param bool $update
     * @return mixed
     */
    public static function aliTicket($key, $v = null, $update = false)
    {
        if ($update) {
            return updateGlobalConfig('ali_ticket', $key, $v);
        }

        return globalConfig('ali_ticket', $key, $v);
    }

    /**
     * 代理商相关配置
     * @param $key
     * @param null $v
     * @param bool $update
     * @return mixed
     */
    public static function agent($key, $v = null, $update = false)
    {
        if ($update) {
            return updateGlobalConfig('agent', $key, $v);
        }

        return globalConfig('agent', $key, $v);
    }

    /**
     * 微信第三方平台相关配置
     */

    public static function wxplatform($key, $v = null, $update = false)
    {
        if ($update) {
            return updateGlobalConfig('wxplatform', $key, $v);
        }

        return globalConfig('wxplatform', $key, $v);
    }
}