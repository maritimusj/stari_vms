<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\util;

use DateTimeInterface;
use Exception;
use zovye\App;
use zovye\We7;
use function zovye\err;
use function zovye\hashFN;

class CacheUtil
{
    public static function expire(string $uid)
    {
        We7::cache_delete(App::uid(6).$uid);
    }

    public static function expiredCall(string $uid, $interval_seconds, callable $fn)
    {
        $key = App::uid(6).$uid;

        $data = We7::cache_read($key);
        if ($data && is_array($data) && ($interval_seconds === 0 || time() - intval(
                    $data['time']
                ) < $interval_seconds)) {
            return $data['v'];
        }

        try {
            $result = $fn();

            We7::cache_write($key, [
                'time' => time(),
                'v' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            return err($e->getMessage());
        }
    }

    /**
     * 缓存指定函数的调用结果，指定时间内不再重复调用
     * @param $interval_seconds 0表示不过期
     * @param callable $fn
     * @param mixed ...$params 用来区分同一个函数应用了不同的参数的情况
     * @return mixed
     */
    public static function cachedCall($interval_seconds, callable $fn, ...$params)
    {
        return self::expiredCall('delay'.hashFN($fn, ...$params), $interval_seconds, $fn);
    }

    public static function cachedCallWhen($interval_seconds, callable $fn, ...$params)
    {
        $key = 'delay'.hashFN($fn, ...$params);

        $data = We7::cache_read($key);
        if ($data && is_array($data) && ($interval_seconds === 0 || time() - intval(
                    $data['time']
                ) < $interval_seconds)) {
            return $data['v'];
        }

        list($result, $v) = $fn();
        if ($v) {
            We7::cache_write($key, [
                'time' => time(),
                'v' => $result,
            ]);
        }

        return $result;
    }

    public static function expiredCallUtil($uid, $expired, $fn)
    {
        $key = App::uid(6).$uid;

        $data = We7::cache_read($key);
        if ($data && is_array($data) && time() <= intval($data['time'])) {
            return $data['v'];
        }

        try {
            $result = $fn();

            We7::cache_write($key, [
                'time' => $expired instanceof DateTimeInterface ? $expired->getTimestamp() : intval($expired),
                'v' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            return err($e->getMessage());
        }
    }

    public static function cachedCallUtil($expired, callable $fn, ...$params)
    {
        return self::expiredCallUtil('expired'.hashFN($fn, ...$params), $expired, $fn);
    }
}