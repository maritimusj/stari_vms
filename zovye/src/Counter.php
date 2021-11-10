<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\counterModelObj;

class Counter
{
    public static function create(array $data = [])
    {
        return m('counter')->create($data);
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return m('counter')->query($condition);
    }

    public static function get($id, bool $is_uid = false)
    {
        if ($is_uid) {
            return self::query(['uid' => $id])->findOne();
        }
        return self::query(['id' => $id])->findOne();
    }

    public static function exists($uid): bool
    {
        return self::query(['uid' => $uid])->exists();
    }

    public static function increment($uid, int $delta = 1, callable $initFN = null, bool $force = false): bool
    {
        if ($delta == 0) {
            return false;
        }

        $result = Util::transactionDo(function () use ($uid, $delta, $initFN, $force) {
            $tb = We7::tablename(counterModelObj::getTableName(true));

            if ($initFN && $force) {
                $v = $initFN();
                if ($v === false || is_error($v)) {
                    return err('initFN return false or error');
                }
                $sql = "UPDATE $tb SET num=$v,updatetime=:updatetime WHERE uid=:uid";
            } else {
                $op = $delta > 0 ? '+' : '';
                $sql = "UPDATE $tb SET num=num$op$delta,updatetime=:updatetime WHERE uid=:uid";
            }

            $uid_arr = is_array($uid) ? $uid : [$uid];
            foreach ($uid_arr as $uid) {
                $params = [
                    ':uid' => $uid,
                    ':updatetime' => time(),
                ];
                $res = We7::pdo_query($sql, $params);
                if ($res < 1) {
                    if (Locker::try("counter:init:$uid")) {
                        $v = $initFN == null ? $delta : $initFN();
                        if ($v === false || is_error($v)) {
                            return err('initFN return false or error');
                        }
                        if (self::create([
                            'uid' => $uid,
                            'num' => $v,
                            'createtime' => time(),
                            'updatetime' => 0,
                        ])) {
                            continue;
                        } else {
                            return err('create db item failed');
                        }
                    }
                    return err('lock failed');
                }
            }
            return true;
        });
        return !is_error($result);
    }

    public static function decrement($uid, $delta = 1): bool
    {
        return self::increment($uid, -$delta);
    }
}