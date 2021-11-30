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
        $condition = $this->fillCondition($params, $begin, $end);
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
            'free' => $this->getHourFreeTotal($objs, $time),
            'pay' => $this->getHourPayTotal($objs, $time),
            'balance' => $this->getHourBalanceTotal($objs, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getHourFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return $this->getHour($time, $params);
    }

    public function getHourPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return $this->getHour($time, $params);
    }

    public function getHourBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getHour($time, $params);
    }

    public function getDayAll($obj, DateTimeInterface $time): array
    {
        $result = [
            'free' => $this->getDayFreeTotal($obj, $time),
            'pay' => $this->getDayPayTotal($obj, $time),
            'balance' => $this->getDayBalanceTotal($obj, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getDayFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return $this->getDay($time, $params);
    }

    public function getDayPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return $this->getDay($time, $params);
    }

    public function getDayBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getDay($time, $params);
    }

    public function getMonthAll($obj, DateTimeInterface $time): array
    {
        $result = [
            'free' => $this->getMonthFreeTotal($obj, $time),
            'pay' => $this->getMonthPayTotal($obj, $time),
            'balance' => $this->getMonthBalanceTotal($obj, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getMonthFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return $this->getMonth($time, $params);
    }

    public function getMonthPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return $this->getMonth($time, $params);
    }

    public function getMonthBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getMonth($time, $params);
    }

    public function getYearAll($obj, DateTimeInterface $time): array
    {
        $result = [
            'free' => $this->getYearFreeTotal($obj, $time),
            'pay' => $this->getYearPayTotal($obj, $time),
            'balance' => $this->getYearBalanceTotal($obj, $time),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];
        return $result;
    }

    public function getYearFreeTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::ACCOUNT;

        return $this->getYear($time, $params);
    }

    public function getYearPayTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::PAY;

        return $this->getYear($time, $params);
    }

    public function getYearBalanceTotal($obj, DateTimeInterface $time): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getYear($time, $params);
    }

}