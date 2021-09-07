<?php

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

    public static function increment(string $uid, int $delta = 1): bool
    {
        if ($delta == 0) {
            return true;
        }

        return Util::transactionDo(function () use ($uid, $delta) {
            $uid_arr = is_array($uid) ? $uid : [$uid];
            $tb = We7::tablename(counterModelObj::getTableName(true));
            $op = $delta > 0 ? '+' : '';

            foreach ($uid_arr as $uid) {
                $sql = "UPDATE $tb SET num=num$op$delta,updatetime=:updatetime WHERE uid=:uid";
                $params = [
                    ':uid' => $uid,
                    ':updatetime' => time(),
                ];
                $res = We7::pdo_query($sql, $params);
                if ($res < 1) {
                    if (self::create([
                        'uid' => $uid,
                        'num' => 0,
                        'createtime' => time(),
                        'updatetime' => 0,
                    ])) {
                        $res = We7::pdo_query($sql, $params);
                        if ($res < 1) {
                            return false;
                        } else {
                            continue;
                        }
                    }
                    return false;
                }
            }
            return true;
        });
    }

    public static function decrement($uid, $delta = 1): bool
    {
        return self::increment($uid, -$delta);
    }
}