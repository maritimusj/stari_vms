<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\base\modelObj;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;

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
        $date->modify('first day of this month 00:00');

        return $date;
    }

    /**
     * @param deviceModelObj|agentModelObj|goodsModelObj|accountModelObj $obj
     * @param DateTimeInterface $begin
     * @param DateTimeInterface $end
     * @return array
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

        $result['free'] = (int)Order::query($condition)->where(['src' => Order::ACCOUNT])->get('sum(num)');
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

    public static function deviceOrder(deviceModelObj $device, $start = '', $end = '')
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
        if (empty($start)) {
            $begin->modify('first day of January this year 00:00');
        } else {
            $begin->modify('today 00:00');
        }
        if (empty($end)) {
            $end = $begin->modify('first day of January next year 00:00');
        } else {
            $end->modify('next day 00:00');
        }

        return Util::cachedCall($end->getTimestamp() > time() ? 10 : 0, function () use ($device, $begin, $end) {
            return self::calc($device, $begin, $end);
        }, $device->getId(), $begin->getTimestamp(), $end->getTimestamp());
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
            $date->modify('first day of January 00:00');
            $end = DateTimeImmutable::createFromMutable($date)->modify('first day of January next year 00:00');

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

    public static function monthData(modelObj $obj, $month = '', $day = 0)
    {
        $counter = new OrderCounter();

        $fn = function (DateTimeInterface $begin, $w = 'day') use ($obj, $counter) {
            $result = [];

            $end = new DateTime($begin->format('Y-m-d 00:00'));
            if ($w == 'day') {
                $result['order'] = $counter->getDayAll([$obj, 'goods'], $begin);
                $end->modify('next day 00:00');
            } elseif ($w == 'month') {
                $result['order'] = $counter->getMonthAll([$obj, 'goods'], $begin);
                $end->modify('first day of next month 00:00');
            } else {
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
                $total = (int)Util::cachedCall(0, function () use ($obj, $begin, $end) {
                    return CommissionBalance::query()->where([
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
                }, get_class($obj), $obj->getId(), $begin->getTimestamp(), $end->getTimestamp());

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

        return Util::cachedCall($begin->format('Y-m') === date('Y-m') ? 10 : 0, function () use ($fn, $day, $begin) {
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
                $start = new DateTimeImmutable($begin->format("Y-m-$day 00:00:00"));
                if ($start->format('Y-m') != $begin->format('Y-m')) {
                    return [];
                }
                $result['list'][$start->format('m月d日')] = $fn($start, 'day');
            }

            return $result;
        }, get_class($obj), $obj->getId(), $begin->format('Y-m'), $day);
    }

    public static function userYear(userModelObj $user, $year = '', $month = 0): array
    {
        return self::yearData($user, $year, $month);
    }

    public static function userMonth(userModelObj $user, $month = '', $day = 0)
    {
        return self::monthData($user, $month, $day);
    }

    public static function accountYear(accountModelObj $account, $year = '', $month = 0): array
    {
        return self::yearData($account, $year, $month);
    }

    public static function accountMonth(accountModelObj $account, $month = '', $day = 0)
    {
        return self::monthData($account, $month, $day);
    }
}
