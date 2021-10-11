<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Closure;
use Exception;
use zovye\model\cacheModelObj;

class Cache
{
    public static function create($data = []): ?cacheModelObj
    {
        return m('cache')->create($data);
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return m('cache')->query($condition);
    }

    /**
     * @return cacheModelObj
     */
    public static function get($id, $is_uid = false): ?cacheModelObj
    {
        if ($is_uid) {
            return self::query(['uid' => $id])->findOne();
        }

        return self::query(['id' => $id])->findOne();
    }

    public static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    public static function ResultExpiredAfter(int $seconds): Closure
    {
        return function (array &$data) use ($seconds) {
            $data['expire_time'] = time() + $seconds;
        };
    }

    public static function ResultExpiredAt($time): Closure
    {
        return function (array &$data) use ($time) {
            $data['expire_time'] = is_int($time) ? $time : strtotime($time);
        };
    }

    public static function ErrorExpiredAfter(int $seconds): Closure
    {
        return function (array &$data) use ($seconds) {
            $data['error_expired'] = time() + $seconds;
        };
    }

    public static function ErrorExpiredAt($time): Closure
    {
        return function (array &$data) use ($time) {
            $data['error_expired'] = is_int($time) ? $time : strtotime($time);;
        };
    }

    public static function Data($v): Closure
    {
        return function (array &$data) use ($v) {
            $data['data'] = $v;
        };
    }

    public static function makeUID(array $v = []): string
    {
        $v = We7::uniacid($v);
        return sha1(http_build_query($v));
    }

    public static function expire($uid)
    {
        $obj = self::get($uid, true);
        if ($obj) {
            $obj->destroy();
        }
    }

    public static function fetch($uid, callable $fn = null, callable ...$args)
    {
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
                if (isset($conf['error_expired'])) {
                    $data['expiretime'] = $conf['error_expired'];
                } else {
                    return $result;
                }
            }

        } elseif (isset($conf['data'])) {
            $res = $conf['data'];
        }

        $data['data'] = json_encode($res);

        $now = time();
        $data['createtime'] = $now;
        $data['updatetime'] = $now;
        if (!isset($data['expiretime'])) {
            $data['expiretime'] = intval($conf['expire_time']);
        }

        if (self::create($data)) {
            return $res;
        }

        return err('保存数据失败！');
    }
}