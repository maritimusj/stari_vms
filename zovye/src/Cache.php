<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Closure;
use Exception;
use zovye\base\modelObj;
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
            $data['expiration_time'] = time() + $seconds;
        };
    }

    public static function ResultExpiredAt($time): Closure
    {
        return function (array &$data) use ($time) {
            $data['expiration_time'] = is_int($time) ? $time : strtotime($time);
        };
    }

    public static function ErrorExpiredAfter(int $seconds): Closure
    {
        return function (array &$data) use ($seconds) {
            $data['error_expiration'] = time() + $seconds;
        };
    }

    public static function ErrorExpiredAt($time): Closure
    {
        return function (array &$data) use ($time) {
            $data['error_expiration'] = is_int($time) ? $time : strtotime($time);;
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
        $arr = [
            We7::uniacid(),
        ];
        foreach ($v as $item) {
            if ($item instanceof modelObj) {
                $arr[] = get_class($item);
                $arr[] = $item->getId();
            } else {
                $arr[] = strval($item);
            }
        }
        return sha1(http_build_query($arr));
    }

    public static function expire($uid)
    {
        $obj = self::get($uid, true);
        if ($obj) {
            $obj->destroy();
        }
    }

    /**
     * @param $obj
     * @param callable|null $fn 初始化方法
     * @param callable ...$args 附加参数
     * @return array|mixed|cacheModelObj|null
     */
    public static function fetch($obj, callable $fn = null, callable ...$args)
    {
        if (is_scalar($obj)) {
            $uid = strval($obj);
        } elseif (is_array($obj)) {
            $uid = self::makeUID($obj);
        } elseif ($obj instanceof modelObj) {
            $uid = self::makeUID([$obj]);
        } else {
            return err('无法生成uid！');
        }

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
            'uid' => $obj,
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