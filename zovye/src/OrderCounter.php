<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTimeImmutable;
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
        $arr = [];

        foreach ($params as $index => $obj) {
            if ($obj instanceof agentModelObj) {
                $arr['agent'] = $obj->getId();
            } elseif ($obj instanceof userModelObj) {
                $arr['user'] = $obj->getId();
            } elseif ($obj instanceof deviceModelObj) {
                $arr['device'] = $obj->getId();
            } elseif ($obj instanceof accountModelObj) {
                $arr['account'] = $obj->getId();
            } elseif ($obj instanceof goodsModelObj) {
                $arr['goods'] = $obj->getId();
            } elseif ($obj instanceof WeApp) {
                $arr['app'] = App::uid();
            } else {
                $arr[$index] = $obj;
            }
        }

        ksort($arr);

        return sha1('order:counter:'.http_build_query($arr));
    }

    protected function initFN(DateTimeInterface $begin, DateTimeInterface $end, array $params = []): int
    {
        $condition = $this->fillCondition($params, $begin, $end);
        if (in_array('goods', $params)) {
            $v = Order::query($condition)->get('sum(num)');
        } elseif (in_array('price', $params)) {
            $v = Order::query($condition)->get('sum(price)');
        } else {
            $v = Order::query($condition)->count();
        }

        return intval($v);
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

    public function getHourAll($obj, DateTimeInterface $time = null): array
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

    public function getHourFreeTotal($obj, DateTimeInterface $hour = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [Order::ACCOUNT, Order::FREE];

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING_SOLO;
        }

        return $this->getHour($params, $hour ?? new DateTimeImmutable());
    }

    public function getHourPayTotal($obj, DateTimeInterface $hour = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [Order::PAY];

        if (App::isChargingDeviceEnabled()) {
            $params['src'][] = Order::CHARGING;
        }

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING;
        }

        return $this->getHour($params, $hour ?? new DateTimeImmutable());
    }

    public function getHourBalanceTotal($obj, DateTimeInterface $hour = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getHour($params, $hour ?? new DateTimeImmutable());
    }

    public function getDayAll($obj, DateTimeInterface $day = null): array
    {
        $result = [
            'free' => $this->getDayFreeTotal($obj, $day),
            'pay' => $this->getDayPayTotal($obj, $day),
            'balance' => $this->getDayBalanceTotal($obj, $day),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];

        return $result;
    }

    public function getDayFreeTotal($obj, DateTimeInterface $day = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [
            Order::ACCOUNT,
            Order::FREE,
        ];

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING_SOLO;
        }

        return $this->getDay($params, $day ?? new DateTimeImmutable());
    }

    public function getDayPayTotal($obj, DateTimeInterface $day = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [Order::PAY];

        if (App::isChargingDeviceEnabled()) {
            $params['src'][] = Order::CHARGING;
        }

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING;
        }

        return $this->getDay($params, $day ?? new DateTimeImmutable());
    }

    public function getDayBalanceTotal($obj, DateTimeInterface $day = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getDay($params, $day ?? new DateTimeImmutable());
    }

    public function getMonthAll($obj, DateTimeInterface $month = null): array
    {
        $result = [
            'free' => $this->getMonthFreeTotal($obj, $month),
            'pay' => $this->getMonthPayTotal($obj, $month),
            'balance' => $this->getMonthBalanceTotal($obj, $month),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];

        return $result;
    }

    public function getMonthFreeTotal($obj, DateTimeInterface $month = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [
            Order::ACCOUNT,
            Order::FREE,
        ];

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING_SOLO;
        }

        return $this->getMonth($params, $month ?? new DateTimeImmutable());
    }

    public function getMonthPayTotal($obj, DateTimeInterface $month = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [Order::PAY];

        if (App::isChargingDeviceEnabled()) {
            $params['src'][] = Order::CHARGING;
        }

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING;
        }

        return $this->getMonth($params, $month ?? new DateTimeImmutable());
    }

    public function getMonthBalanceTotal($obj, DateTimeInterface $month = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getMonth($params, $month ?? new DateTimeImmutable());
    }

    public function getYearAll($obj, DateTimeInterface $year = null): array
    {
        $result = [
            'free' => $this->getYearFreeTotal($obj, $year),
            'pay' => $this->getYearPayTotal($obj, $year),
            'balance' => $this->getYearBalanceTotal($obj, $year),
        ];

        $result['total'] = $result['free'] + $result['pay'] + $result['balance'];

        return $result;
    }

    public function getYearFreeTotal($obj, DateTimeInterface $year = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [Order::ACCOUNT, Order::FREE];

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING_SOLO;
        }

        return $this->getYear($params, $year ?? new DateTimeImmutable());
    }

    public function getYearPayTotal($obj, DateTimeInterface $year = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];

        $params['src'] = [Order::PAY];

        if (App::isChargingDeviceEnabled()) {
            $params['src'][] = Order::CHARGING;
        }

        if (App::isFuelingDeviceEnabled()) {
            $params['src'][] = Order::FUELING;
        }

        return $this->getYear($params, $year ?? new DateTimeImmutable());
    }

    public function getYearBalanceTotal($obj, DateTimeInterface $year = null): int
    {
        $params = is_array($obj) ? $obj : [$obj];
        $params['src'] = Order::BALANCE;

        return $this->getYear($params, $year ?? new DateTimeImmutable());
    }
}