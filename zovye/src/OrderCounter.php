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
        sort($arr);
        return sha1(http_build_query($arr));
    }

    public static function fillCondition(array $objs, DateTimeInterface $begin, DateTimeInterface $end, $param = []): array
    {
        $condition = array_merge([], $param);
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

    public static function getHour(array $objs, DateTimeInterface $time, $params = [], $sumGoods = false): int
    {
        $extra = [
            $time->format('YmdH'),
        ];
        if ($sumGoods) {
            $extra[] = 'goods';
        }

        $uid = self::makeUID($objs, array_merge($extra, $params));

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        try {
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

        } catch (Exception $e) {
        }
        return 0;
    }

    public static function getDay(array $objs, DateTimeInterface $time, $params = [], $sumGoods = false): int
    {
        $extra = [
            $time->format('Ymd'),
        ];
        if ($sumGoods) {
            $extra[] = 'goods';
        }

        $uid = self::makeUID($objs, array_merge($extra, $params));

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

        $uid = self::makeUID($objs, array_merge($extra, $params));

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

        $uid = self::makeUID($objs, array_merge($extra, $params));

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

    public static function getHourAll($obj, DateTimeInterface $time): array
    {
        $objs = is_array($obj) ? $obj : [$obj];
        $result = [
            'free' => self::getHourFree($objs, $time),
            'pay' => self::getHourPay($objs, $time),
            'balance' => self::getHourBalance($objs, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public static function getHourFree($obj, DateTimeInterface $time): int
    {
        return self::getHour(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::ACCOUNT]);
    }

    public static function getHourPay($obj, DateTimeInterface $time): int
    {
        return self::getHour(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::PAY]);
    }

    public static function getHourBalance($obj, DateTimeInterface $time): int
    {
        return self::getHour(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::BALANCE]);
    }

    public static function getDayAll($obj, DateTimeInterface $time): array
    {
        $objs = is_array($obj) ? $obj : [$obj];
        $result = [
            'free' => self::getDayFree($objs, $time),
            'pay' => self::getDayPay($objs, $time),
            'balance' => self::getDayBalance($objs, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public static function getDayFree($obj, DateTimeInterface $time): int
    {
        return self::getDay(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::ACCOUNT]);
    }

    public static function getDayPay($obj, DateTimeInterface $time): int
    {
        return self::getDay(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::PAY]);
    }

    public static function getDayBalance($obj, DateTimeInterface $time): int
    {
        return self::getDay(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::BALANCE]);
    }

    public static function getMonthAll($obj, DateTimeInterface $time): array
    {
        $objs = is_array($obj) ? $obj : [$obj];
        $result = [
            'free' => self::getMonthFree($objs, $time),
            'pay' => self::getMonthPay($objs, $time),
            'balance' => self::getMonthBalance($objs, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public static function getMonthFree($obj, DateTimeInterface $time): int
    {
        return self::getMonth(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::ACCOUNT]);
    }

    public static function getMonthPay($obj, DateTimeInterface $time): int
    {
        return self::getMonth(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::PAY]);
    }

    public static function getMonthBalance($obj, DateTimeInterface $time): int
    {
        return self::getMonth(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::BALANCE]);
    }

    public static function getYearAll($obj, DateTimeInterface $time): array
    {
        $objs = is_array($obj) ? $obj : [$obj];
        $result = [
            'free' => self::getYearFree($objs, $time),
            'pay' => self::getYearPay($objs, $time),
            'balance' => self::getYearBalance($objs, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public static function getYearFree($obj, DateTimeInterface $time): int
    {
        return self::getYear(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::ACCOUNT]);
    }

    public static function getYearPay($obj, DateTimeInterface $time): int
    {
        return self::getYear(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::PAY]);
    }

    public static function getYearBalance($obj, DateTimeInterface $time): int
    {
        return self::getYear(is_array($obj) ? $obj : [$obj], $time, ['src' => Order::BALANCE]);
    }
}