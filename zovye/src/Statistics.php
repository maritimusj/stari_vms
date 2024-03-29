<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\base\ModelObj;
use zovye\domain\Balance;
use zovye\domain\CommissionBalance;
use zovye\domain\Order;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;
use zovye\util\OrderCounter;

class Statistics
{
    private static function parseMonth($month): ?DateTime
    {
        $date = null;

        try {
            if (is_int($month)) {
                $date = new DateTime('@'.$month);
            } elseif (is_string($month)) {
                $date = new DateTime($month);
            } elseif ($month instanceof DateTimeInterface) {
                $date = new DateTime($month->format('Y-m-d'));
            }
        } catch (Exception $e) {
            return null;
        }

        return $date->modify('first day of this month 00:00');
    }

    /**
     * @param deviceModelObj|agentModelObj|goodsModelObj|accountModelObj $obj
     */
    public static function calc($obj, DateTimeInterface $begin, DateTimeInterface $end): array
    {
        $condition = [
            'createtime >=' => $begin->getTimestamp(),
            'createtime <' => $end->getTimestamp(),
        ];

        if ($obj instanceof deviceModelObj) {
            $condition['device_id'] = $obj->getId();
        } elseif ($obj instanceof agentModelObj) {
            $condition['agent_id'] = $obj->getId();
        } elseif ($obj instanceof goodsModelObj) {
            $condition['goods_id'] = $obj->getId();
        } elseif ($obj instanceof accountModelObj) {
            $condition['account'] = $obj->getName();
        } else {
            return [];
        }

        $result = [];

        $result['free'] = (int)Order::query($condition)->where(['src' => [Order::ACCOUNT, Order::FREE]])->get(
            'sum(num)'
        );
        $result['pay'] = (int)Order::query($condition)->where(['src' => Order::PAY])->get('sum(num)');

        if (App::isChargingDeviceEnabled()) {
            $result['pay'] += (int)Order::query($condition)->where(['src' => Order::CHARGING])->get('sum(num)');
        }

        $balance = (int)Order::query($condition)->where(['src' => Order::BALANCE])->get('sum(num)');

        if (App::isBalanceEnabled()) {
            if (Balance::isPayOrder()) {
                $result['pay'] += $balance;
            } elseif (Balance::isFreeOrder()) {
                $result['free'] += $balance;
            }
        }

        $result['total'] = $result['pay'] + $result['free'];

        return $result;
    }

    public static function deviceOrder(deviceModelObj $device, $start = '', $end = ''): array
    {
        try {
            $begin = new DateTime($start);
        } catch (Exception $e) {
            return [];
        }
        try {
            $end = new DateTime($end);
        } catch (Exception $e) {
            return [];
        }

        $begin->modify('first day of Jan this year 00:00');

        $end->modify('next day 00:00');

        return self::calc($device, $begin, $end);
    }

    public static function deviceOrderMonth(deviceModelObj $device, $month = ''): array
    {
        $date = self::parseMonth($month);
        if (!$date) {
            return [];
        }

        $begin = DateTimeImmutable::createFromMutable($date);

        $data = (new OrderCounter())->getMonthAll([$device, 'goods'], $begin);

        if (App::isBalanceEnabled()) {
            if (Balance::isPayOrder()) {
                $data['pay'] += $data['balance'];
            } elseif (Balance::isFreeOrder()) {
                $data['free'] += $data['balance'];
            }
        }

        return $data;
    }

    protected static function yearData($obj, $year = '', $month = 0): array
    {
        $result = [
            'summary' => [
                'order' => [
                    'free' => 0,
                    'pay' => 0,
                    'total' => 0,
                ],
                'commission' => [
                    'total' => 0,
                ],
            ],
            'list' => [],
        ];

        $date = self::parseMonth($year);
        if (!$date) {
            return $result;
        }

        $fn = function ($data, $start) use (&$result) {
            $data['summary']['m'] = $start->format('Y年m月');
            $result['summary']['order']['free'] += $data['summary']['order']['free'];
            $result['summary']['order']['pay'] += $data['summary']['order']['pay'];
            $result['summary']['order']['total'] += $data['summary']['order']['total'];
            if (isset($result['summary']['commission']['total'])) {
                $result['summary']['commission']['total'] += $data['summary']['commission']['total'];
            }
            $result['list'][$start->format('m月')] = $data['summary'];
        };

        if ($month == 0) {
            $date->modify('first day of Jan 00:00');
            $end = DateTimeImmutable::createFromMutable($date)->modify('first day of Jan next year 00:00');

            while ($date < $end) {
                if ($date->getTimestamp() > time()) {
                    break;
                }
                $start = DateTimeImmutable::createFromMutable($date);
                $date->modify('next month 00:00');
                $data = self::monthData($obj, $start);
                $fn($data, $start);
            }
        } else {
            $date->modify('first day of this month 00:00');
            $data = self::monthData($obj, $date, 1);
            $fn($data, $date);
        }

        return $result;
    }

    public static function monthData(ModelObj $obj, $month = '', $day = 0): array
    {
        $fn = function (DateTimeInterface $begin, $w = 'day') use ($obj) {
            $result = [];
            try {
                $end = new DateTime($begin->format('Y-m-d 00:00'));
                if ($w == 'day') {
                    $result['order'] = (new OrderCounter())->getDayAll([$obj, 'goods'], $begin);
                    $end->modify('next day 00:00');
                } elseif ($w == 'month') {
                    $result['order'] = (new OrderCounter())->getMonthAll([$obj, 'goods'], $begin);
                    $end->modify('first day of next month 00:00');
                } else {
                    return [];
                }
            } catch (Exception $e) {
                return [];
            }

            if (App::isBalanceEnabled()) {
                if (Balance::isPayOrder()) {
                    $result['order']['pay'] += $result['order']['balance'];
                } elseif (Balance::isFreeOrder()) {
                    $result['order']['free'] += $result['order']['balance'];
                }
            }

            if ($obj instanceof agentModelObj) {
                $total = CommissionBalance::query()->where([
                    'openid' => $obj->getOpenid(),
                    'src' => [
                        CommissionBalance::ORDER_FREE,
                        CommissionBalance::ORDER_BALANCE,
                        CommissionBalance::ORDER_WX_PAY,
                        CommissionBalance::ORDER_REFUND,
                        CommissionBalance::GSP,
                        CommissionBalance::BONUS,
                    ],
                    'createtime >=' => $begin->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->get('sum(x_val)');

                $result['commission'] = [
                    'total' => number_format($total / 100, 2, '.', ''),
                ];
            }

            return $result;
        };

        $begin = self::parseMonth($month);
        if (!$begin) {
            return [];
        }

        $result = [
            'summary' => $fn($begin, 'month'),
        ];

        if ($day === true) {
            $result['list'] = [];
            $end = DateTimeImmutable::createFromMutable($begin)->modify('first day of next month 00:00');
            while ($begin < $end) {
                $start = DateTimeImmutable::createFromMutable($begin);
                $begin->modify('next day 00:00');
                $result['list'][$start->format('m月d日')] = $fn($start, 'day');
            }
        } elseif ($day > 0) {
            $result['list'] = [];
            try {
                $start = new DateTimeImmutable($begin->format("Y-m-$day 00:00:00"));

                if ($start->format('Y-m') != $begin->format('Y-m')) {
                    return [];
                }
                $result['list'][$start->format('m月d日')] = $fn($start, 'day');
            } catch (Exception $e) {
            }
        }

        return $result;
    }

    public static function userYear(userModelObj $user, $year = '', $month = 0): array
    {
        return self::yearData($user, $year, $month);
    }

    public static function userMonth(userModelObj $user, $month = '', $day = 0): array
    {
        return self::monthData($user, $month, $day);
    }

    public static function accountYear(accountModelObj $account, $year = '', $month = 0): array
    {
        return self::yearData($account, $year, $month);
    }

    public static function accountMonth(accountModelObj $account, $month = '', $day = 0): array
    {
        return self::monthData($account, $month, $day);
    }
}
