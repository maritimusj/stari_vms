<?php

namespace zovye;

use DateTimeInterface;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;

class OrderCounter extends StatsCounter
{
    protected function makeUID(array $params = []): string
    {
        $arr = ["order:counter"];

        foreach ($params as $index => $obj) {
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
            } else {
                $arr[$index] = $obj;
            }
        }

        sort($arr);
        return sha1(http_build_query($arr));
    }

    protected function initFN(DateTimeInterface $begin, DateTimeInterface $end, array $params = [])
    {
        $condition = self::fillCondition($params, $begin, $end);
        if (in_array('goods', $params)) {
            $v = Order::query($condition)->get('sum(num)');
        } else {
            $v = Order::query($condition)->count();
        }
        return $v;
    }

    protected function fillCondition(array $params, DateTimeInterface $begin, DateTimeInterface $end): array
    {
        $condition = [];
        foreach ($params as $index => $obj) {
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
            } elseif ($index == 'src') {
                $condition['src'] = $obj;
            }
        }
        $condition['createtime >='] = $begin->getTimestamp();
        $condition['createtime <'] = $end->getTimestamp();

        return $condition;
    }

    public function getHourAll($obj, DateTimeInterface $time): array
    {
        $objs = is_array($obj) ? $obj : [$obj];
        $result = [
            'free' => self::getHourFreeTotal($objs, $time),
            'pay' => self::getHourPayTotal($objs, $time),
            'balance' => self::getHourBalanceTotal($objs, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getHourFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return self::getHour($time, $params);
    }

    public function getHourPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return self::getHour($time, $params);
    }

    public function getHourBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return self::getHour($time, $params);
    }

    public function getDayAll($obj, DateTimeInterface $time): array
    {
        $result = [
            'free' => self::getDayFreeTotal($obj, $time),
            'pay' => self::getDayPayTotal($obj, $time),
            'balance' => self::getDayBalanceTotal($obj, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getDayFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return self::getDay($time, $params);
    }

    public function getDayPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return self::getDay($time, $params);
    }

    public function getDayBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return self::getDay($time, $params);
    }

    public function getMonthAll($obj, DateTimeInterface $time): array
    {
        $result = [
            'free' => self::getMonthFreeTotal($obj, $time),
            'pay' => self::getMonthPayTotal($obj, $time),
            'balance' => self::getMonthBalanceTotal($obj, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getMonthFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return self::getMonth($time, $params);
    }

    public function getMonthPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return self::getMonth($time, $params);
    }

    public function getMonthBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return self::getMonth($time, $params);
    }

    public function getYearAll($obj, DateTimeInterface $time): array
    {
        $result = [
            'free' => self::getYearFreeTotal($obj, $time),
            'pay' => self::getYearPayTotal($obj, $time),
            'balance' => self::getYearBalanceTotal($obj, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getYearFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return self::getYear($time, $params);
    }

    public function getYearPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return self::getYear($time, $params);
    }

    public function getYearBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return self::getYear($time, $params);
    }

}