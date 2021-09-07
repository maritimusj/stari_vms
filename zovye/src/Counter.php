<?php

namespace zovye;

use zovye\model\counterModelObj;

class Counter
{
    public static function create(array $data = [])
    {
        var_dump($data);
        $result = m('counter')->create($data);
        var_dump($result);
        return $result;
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

    public static function increment($uid, int $delta = 1): bool
    {
        if ($delta == 0) {
            return true;
        }

        return Util::transactionDo(function () use ($uid, $delta) {
            $tb = We7::tablename(counterModelObj::getTableName(true));
            $op = $delta > 0 ? '+' : '';
            $sql = "UPDATE $tb SET num=num$op$delta,updatetime=:updatetime WHERE uid=:uid";

            $uid_arr = is_array($uid) ? $uid : [$uid];
            foreach ($uid_arr as $uid) {
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
                            return err('failed');
                        } else {
                            continue;
                        }
                    }
                    return err('failed');
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