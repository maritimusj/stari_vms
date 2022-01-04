<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
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
                $date = new DateTime('@' . $month);
            } elseif (is_string($month)) {
                $date = new DateTime($month);
            } elseif ($month instanceof DateTimeInterface) {
                $date = DateTime::createFromFormat('Y-m-d', $month->format('Y-m-d'));
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
        $result = [];

        $condition = [
            'createtime >=' => $begin->getTimestamp(),
            'createtime <' => $end->getTimestamp()
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

        $result['free'] = (int)Order::query($condition)->where(['src' => Order::ACCOUNT])->get('sum(num)');
        $result['pay'] = (int)Order::query($condition)->where(['src' => Order::PAY])->get('sum(num)');

        $balance = (int)Order::query($condition)->where(['src' => Order::BALANCE])->get('sum(num)');

        if (Balance::isPayOrder()) {
            $result['pay'] += $balance;
        } elseif (Balance::isFreeOrder()) {
            $result['free'] += $balance;
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

    public static function deviceOrderMonth(deviceModelObj $device, $month = '')
    {
        $date = self::parseMonth($month);
        if (!$date) {
            return [];
        }

        $begin = DateTimeImmutable::createFromMutable($date);
        $end = $begin->modify('first day of next month 00:00');

        return Util::cachedCall($begin->format('Y-m') === date('Y-m') ? 10 : 0, function () use ($begin, $end, $device) {
            return self::calc($device, $begin, $end);
        }, $device->getId(), $begin->format('Y-m'));
    }

    public static function userYear(userModelObj $user, $year = '', $month = 0): array
    {
        $date = self::parseMonth($year);
        if (!$date) {
            return [];
        }


        $result = [
            'summary' => [
                'order' => [
                    'free' => 0,
                    'pay' => 0,
                ],
                'commission' => [
                    'total' => 0,
                ],
            ],
            'list' => []
        ];

        $fn = function ($data, $start) use ($result) {
            $data['summary']['m'] = $start->format('Y年m月');
            $result['summary']['order']['free'] += $data['summary']['order']['free'];
            $result['summary']['order']['pay'] += $data['summary']['order']['pay'];
            $result['summary']['commission']['total'] += $data['summary']['commission']['total'];
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
                $data = self::userMonth($user, $start);
                $fn($data, $start);
            }
        } else {
            $date->modify('first day of this month 00:00');
            $data = self::userMonth($user, $date, 1);
            $fn($data, $date);
        }

        return $result;
    }

    public static function userMonth(userModelObj $user, $month = '', $day = 0)
    {
        $fn = function (DateTimeInterface $begin, DateTimeInterface $end) use ($user) {
            $result = [
                'order' => [
                    'free' => 0,
                    'pay' => 0,
                ],
                'commission' => [
                    'total' => 0,
                ]
            ];

            $result['order']['free'] = (int)Util::cachedCall(0, function () use ($user, $begin, $end) {
                return Order::query()->where([
                    'agent_id' => $user->getId(),
                    'src' => Order::ACCOUNT,
                    'createtime >=' => $begin->getTimestamp(),
                    'createtime <' => $end->getTimestamp()
                ])->get('sum(num)');
            }, $user->getId(), $begin->getTimestamp(), $end->getTimestamp());

            $result['order']['pay'] = (int)Util::cachedCall(0, function () use ($user, $begin, $end) {
                return Order::query()->where([
                    'agent_id' => $user->getId(),
                    'src' => Order::PAY,
                    'createtime >=' => $begin->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->get('sum(num)');
            }, $user->getId(), $begin->getTimestamp(), $end->getTimestamp());

            $balance = (int)Util::cachedCall(0, function () use ($user, $begin, $end) {
                return Order::query()->where([
                    'agent_id' => $user->getId(),
                    'src' => Order::BALANCE,
                    'createtime >=' => $begin->getTimestamp(),
                    'createtime <' => $end->getTimestamp(),
                ])->get('sum(num)');
            }, $user->getId(), $begin->getTimestamp(), $end->getTimestamp());

            if (Balance::isPayOrder()) {
                $result['order']['pay'] += $balance;
            } elseif (Balance::isFreeOrder()) {
                $result['order']['free'] += $balance;
            }

            $result['commission']['total'] = number_format((int)Util::cachedCall(0, function () use ($user, $begin, $end) {
                    return CommissionBalance::query()->where([
                        'openid' => $user->getOpenid(),
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
                }, $user->getId(), $begin->getTimestamp(), $end->getTimestamp()) / 100, 2, '.', '');

            return $result;
        };

        $begin = self::parseMonth($month);
        if (!$begin) {
            return [];
        }

        return Util::cachedCall($begin->format('Y-m') === date('Y-m') ? 10 : 0, function () use ($fn, $day, $begin) {
            $end = DateTimeImmutable::createFromMutable($begin)->modify('first day of next month 00:00');
            $result = [
                'summary' => $fn($begin, $end),
            ];

            if ($day === true) {
                $result['list'] = [];
                while ($begin < $end) {
                    $start = DateTimeImmutable::createFromMutable($begin);
                    $begin->modify('next day 00:00');
                    $result['list'][$start->format('m月d日')] = $fn($start, $begin);
                }
            } elseif ($day > 0) {
                $result['list'] = [];
                $start = new DateTimeImmutable($begin->format("Y-m-$day 00:00:00"));
                if ($start->format('Y-m') != $begin->format('Y-m')) {
                    return [];
                }
                $end = $start->modify('next day 00:00');
                $result['list'][$start->format('m月d日')] = $fn($start, $end);
            }

            return $result;
        }, $user->getId(), $begin->format('Y-m'), $day);
    }
}
