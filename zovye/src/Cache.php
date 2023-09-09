<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Closure;
use DateTimeImmutable;
use Exception;
use zovye\model\base\modelObj;
use zovye\model\cacheModelObj;

class Cache
{
    protected static function create($data = []): ?cacheModelObj
    {
        return m('cache')->create($data);
    }

    protected static function query($condition = []): model\base\modelObjFinder
    {
        return m('cache')->query($condition);
    }

    /**
     * @return cacheModelObj
     */
    protected static function get($id, $is_uid = false): ?cacheModelObj
    {
        if ($is_uid) {
            return self::query(['uid' => $id])->findOne();
        }

        return self::query(['id' => $id])->findOne();
    }

    protected static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    public static function resultExpiredAfter(int $seconds): Closure
    {
        return function (array &$data) use ($seconds) {
            $data['expiration_time'] = time() + $seconds;
        };
    }

    public static function resultExpiredAt($time): Closure
    {
        return function (array &$data) use ($time) {
            $data['expiration_time'] = is_int($time) ? $time : (new DateTimeImmutable($time))->getTimestamp();
        };
    }

    public static function errorExpiredAfter(int $seconds): Closure
    {
        return function (array &$data) use ($seconds) {
            $data['error_expiration'] = time() + $seconds;
        };
    }

    public static function errorExpiredAt($time): Closure
    {
        return function (array &$data) use ($time) {
            $data['error_expiration'] = is_int($time) ? $time : (new DateTimeImmutable($time))->getTimestamp();
        };
    }

    public static function data($v): Closure
    {
        return function (array &$data) use ($v) {
            $data['data'] = $v;
        };
    }

    public static function makeUID($v): string
    {
        $arr = We7::uniacid([]);

        $v = is_array($v) ? $v : [$v];
        foreach ($v as $index => $item) {
            if ($item instanceof modelObj) {
                $arr[$index] = get_class($item);
                $arr[get_class($item)] = $item->getId();
            } else {
                $arr[$index] = strval($item);
            }
        }

        return sha1(http_build_query($arr));
    }

    public static function expire($uid)
    {
        $obj = self::get(self::makeUID($uid), true);
        if ($obj) {
            $obj->destroy();
        }
    }

    public static function set($obj, $v): ?cacheModelObj
    {
        $uid = self::makeUID($obj);

         if (Locker::try($uid)) {

             /** @var cacheModelObj $result */
             $result = self::get($uid, true);
             if ($result) {
                 $result->setData(json_encode($v));
                 $result->setUpdatetime(time());
                 $result->save();
                 return $result;
             }

             $data = [
                 'uid' => $uid,
             ];

             $data['data'] = json_encode($v);

             $now = time();
             $data['createtime'] = $now;
             $data['updatetime'] = $now;

             return self::create($data);
         }

         return null;
    }

    /**
     * @param $obj
     * @param callable|null $fn 初始化方法
     * @param callable ...$args 附加参数
     * @return array|mixed|cacheModelObj|null
     */
    public static function fetch($obj, callable $fn = null, callable ...$args)
    {
        $uid = self::makeUID($obj);

        /** @var cacheModelObj $result */
        $result = self::get($uid, true);
        if ($result) {
            if ($result->isExpired()) {
                $result->destroy();
            } else {
                return json_decode($result->getData(), true);
            }
        }

        $conf = [];
        foreach ($args as $setter) {
            $setter($conf);
        }

        $data = [
            'uid' => $uid,
        ];

        $res = null;

        if ($fn) {
            try {
                $res = $fn();
            } catch (Exception $e) {
                $res = err($e->getMessage());
            }

            if (is_error($res)) {
                if (isset($conf['error_expiration'])) {
                    $data['expiration'] = $conf['error_expiration'];
                } else {
                    return $result;
                }
            }

        } elseif (isset($conf['data'])) {
            $res = $conf['data'];
        }

        if (is_null($res)) {
            return null;
        }

        $data['data'] = json_encode($res);

        $now = time();
        $data['createtime'] = $now;
        $data['updatetime'] = $now;
        if (!isset($data['expiration'])) {
            $data['expiration'] = intval($conf['expiration_time']);
        }

        if (self::create($data)) {
            return $res;
        }

        return err('保存数据失败！');
    }
}