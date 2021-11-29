<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\counterModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;

class OrderCounter
{
    public static function makeUID(array $objs, $extra = []): string
    {
        $arr = ["order:counter"];
        foreach ($objs as $obj) {
            if ($obj instanceof agentModelObj) {
                $arr[] = "agent:{$obj->getId()}";
            } elseif ($obj instanceof userModelObj) {
                $arr[] = "user:{$obj->getId()}";
            } elseif ($obj instanceof deviceModelObj) {
                $arr[] = "device:{$obj->getId()}";
            } elseif ($obj instanceof accountModelObj) {
                $arr[] = "account:{$obj->getId()}";
            } elseif ($obj instanceof goodsModelObj) {
                $arr[] = "goods:{$obj->getId()}";
            } elseif ($obj instanceof WeApp) {
                $uid = App::uid();
                $arr[] = "app:$uid";
            }
        }
        $arr = array_merge($arr, $extra);
        rsort($arr);
        return sha1(implode(':', $arr));
    }

    public static function fillCondition(array $objs, DateTimeInterface $begin, DateTimeInterface $end, $param = [])
    {
        $condition = $param;
        foreach ($objs as $obj) {
            if ($obj instanceof agentModelObj) {
                $condition['agent_id'] = $obj->getId();
            } elseif ($obj instanceof userModelObj) {
                $condition['openid'] = $obj->getOpenid();
            } elseif ($obj instanceof deviceModelObj) {
                $condition['device_id'] = $obj->getId();
            } elseif ($obj instanceof accountModelObj) {
                $condition['account'] = $obj->getName();
            } elseif ($obj instanceof goodsModelObj) {
                $condition['goods_id'] = $obj->getId();
            } elseif ($obj instanceof WeApp) {
                $condition['uniacid'] = We7::uniacid();
            }
        }
        $condition['createtime >='] = $begin->getTimestamp();
        $condition['createtime <'] = $end->getTimestamp();
        return $condition;
    }

    /**
     * @throws Exception
     */
    public static function getHour(array $objs, DateTimeInterface $time, $params = [], $sumGoods = false): int
    {
        $extra = [
            $time->format('YmdH'),
        ];
        if ($sumGoods) {
            $extra[] = 'goods';
        }

        $uid = self::makeUID($objs, $extra);

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        $begin = new DateTimeImmutable($time->format('Y-m-d H:00:00'));
        $end = $begin->modify('+1 hour');

        $condition = self::fillCondition($objs, $begin, $end, $params);
        if ($sumGoods) {
            $v = Order::query($condition)->get('sum(num)');
        } else {
            $v = Order::query($condition)->count();
        }

        if ($time->format('YmdH') != date('YmdH') && Locker::try("counter:init:$uid")) {
            Counter::create([
                'uid' => $uid,
                'num' => $v,
                'createtime' => time(),
                'updatetime' => 0,
            ]);
        }
        return $v;
    }

    public static function getDay(array $objs, DateTimeInterface $time, $params = [], $sumGoods = false): int
    {
        $extra = [
            $time->format('Ymd'),
        ];
        if ($sumGoods) {
            $extra[] = 'goods';
        }

        $uid = self::makeUID($objs, $extra);
      

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        try {
            $begin = new DateTime($time->format('Y-m-d 00:00'));
            $end = new DateTime($time->format('Y-m-d 00:00'));
 
            $end->modify('next day 00:00');
            if ($end->getTimestamp() > time()) {
                $end->setTimestamp(time());
            }

            $total = 0;

            while ($begin < $end) {
                $total += self::getHour($objs, $begin, $params, $sumGoods);
                $begin->modify('+1 hour');
            }

            if ($time->format('Ymd') != date('Ymd') && Locker::try("counter:init:$uid")) {
                Counter::create([
                    'uid' => $uid,
                    'num' => $total,
                    'createtime' => time(),
                    'updatetime' => 0,
                ]);
            }

            return $total;

        } catch (Exception $e) {
        }

        return 0;
    }

    public static function getMonth(array $objs, DateTimeInterface $time, $params = [], $sumGoods = false): int
    {
        $extra = [
            $time->format('Ym'),
        ];
        if ($sumGoods) {
            $extra[] = 'goods';
        }

        $uid = self::makeUID($objs, $extra);

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        try {
            $begin = new DateTime($time->format('Y-m 00:00'));
            $end = new DateTime($time->format('Y-m 00:00'));

            $end->modify("first day of next month 00:00");
            if ($end->getTimestamp() > time()) {
                $end->setTimestamp(time());
            }

            $total = 0;

            while ($begin < $end) {
                $total += self::getDay($objs, $begin, $params, $sumGoods);
                $begin->modify('next day');
            }

            if ($time->format('Ym') != date('Ym') && Locker::try("counter:init:$uid")) {
                Counter::create([
                    'uid' => $uid,
                    'num' => $total,
                    'createtime' => time(),
                    'updatetime' => 0,
                ]);
            }
            return $total;

        } catch (Exception $e) {
        }

        return 0;
    }

    public static function getYear(array $objs, DateTimeInterface $time, $params = [], $sumGoods = false): int
    {
        $extra = [
            $time->format('Y'),
        ];
        if ($sumGoods) {
            $extra[] = 'goods';
        }

        $uid = self::makeUID($objs, $extra);

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        try {
            $begin = new DateTime($time->format('Y-01-01 00:00'));
            $end = new DateTime($time->format('Y-01-01 00:00'));

            $end->modify("first day of next year 00:00");
            if ($end->getTimestamp() > time()) {
                $end->setTimestamp(time());
            }

            $total = 0;

            while ($begin < $end) {
                $total += self::getMonth($objs, $begin, $params, $sumGoods);
                $begin->modify('next month');
            }

            if ($time->format('Y') != date('Y') && Locker::try("counter:init:$uid")) {
                Counter::create([
                    'uid' => $uid,
                    'num' => $total,
                    'createtime' => time(),
                    'updatetime' => 0,
                ]);
            }
            return $total;

        } catch (Exception $e) {
        }
        return 0;
    }
}