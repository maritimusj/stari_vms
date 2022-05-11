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
    /**
     * 代理商相关配置
     * @param $key
     * @param null $v
     * @param bool $update
     * @return mixed
     */
    public static function agent($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('agent', $key, $v);
        }

        return globalConfig('agent', $key, $v);
    }

    /**
     * 用户相关配置
     * @param $key
     * @param null $v
     * @param bool $update
     * @return mixed
     */
    public static function user($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('user', $key, $v);
        }

        return globalConfig('user', $key, $v);
    }


    /**
     * 微信第三方平台相关配置
     */

    public static function wxplatform($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('wxplatform', $key, $v);
        }

        return globalConfig('wxplatform', $key, $v);
    }

    /**
     * 轻松筹爱心捐款设置
     */

    public static function donatePay($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('donatePay', $key, $v);
        }

        return globalConfig('donatePay', $key, $v);
    }

    /**
     * 全局设备设置
     */

    public static function device($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('device', $key, $v);
        }

        return globalConfig('device', $key, $v);
    }

    /**
     * 全局APP设置
     */

    public static function app($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('app', $key, $v);
        }

        return globalConfig('app', $key, $v);
    }

    /**
     * LBS设置
     */

    public static function location($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('location', $key, $v);
        }

        return globalConfig('location', $key, $v);
    }

    /**
     *  抖音设置
     */

    public static function douyin($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('douyin', $key, $v);
        }

        return globalConfig('douyin', $key, $v);
    }

    /**
     *  积分、余额设置
     */

    public static function balance($key, $v = null, bool $update = false)
    {
        if ($update) {
            return updateGlobalConfig('balance', $key, $v);
        }

        return globalConfig('balance', $key, $v);
    }
}