<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\model\deviceModelObj;
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

    public static function deviceOrderMonth(deviceModelObj $device, $month = '')
    {
        $date = self::parseMonth($month);
        if (!$date) {
            return [];
        }

        $begin = DateTimeImmutable::createFromMutable($date);

        return Util::cachedCall($begin->format('Y-m') === date('Y-m') ? 10 : 0, function () use ($begin, $device) {
            $end = $begin->modify('first day of next month 00:00');

            $result = [
                'free' => 0,
                'fee' => 0,
                'balance' => 0,
                'total' => 0,
            ];

            $free = Order::query()->where([
                'device_id' => $device->getId(),
                'price' => 0,
                'balance' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp()
            ])->get('sum(num)');

            $result['free'] = intval($free);

            $fee = Order::query()->where([
                'device_id' => $device->getId(),
                'price >' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ])->get('sum(num)');

            $result['fee'] = intval($fee);

            $balance = Order::query()->where([
                'device_id' => $device->getId(),
                'balance >' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ])->get('sum(num)');

            $result['balance'] = intval($balance);

            $result['total'] = $result['fee'] + $result['free'] + $result['balance'];

            return $result;
        }, $device->getId(), $begin->format('Y-m'));
    }

    public static function userYear(userModelObj $user, $year = '')
    {
        $date = self::parseMonth($year);
        if (!$date) {
            return [];
        }

        $date->modify('first day of January 00:00');
        $end = DateTimeImmutable::createFromMutable($date)->modify('first day of January next year 00:00');

        $result = [
            'summary' => [
                'order' => [
                    'free' => 0,
                    'fee' => 0,
                ],
                'commission' => [
                    'total' => 0,
                ],
            ],
            'list' => []
        ];
        while ($date < $end) {
            if ($date->getTimestamp() > time()) {
                break;
            }
            $start = DateTimeImmutable::createFromMutable($date);
            $date->modify('next month 00:00');
            $data = self::userMonth($user, $start, true);
            $result['summary']['order']['free'] += $data['summary']['order']['free'];
            $result['summary']['order']['fee'] += $data['summary']['order']['fee'];
            $result['summary']['commission']['total'] += $data['summary']['commission']['total'];
            $result['list'][$start->format('m月')] = $data['summary'];
        }
        return $result;
    }

    public static function userMonth(userModelObj $user, $month = '', $detail = false)
    {
        $fn = function (DateTimeInterface $begin, DateTimeInterface $end) use ($user) {
            $result = [
                'order' => [
                    'free' => 0,
                    'fee' => 0,
                ],
                'commission' => [
                    'total' => 0,
                ]
            ];

            $result['order']['free'] = //random_int(0, 1000);
            (int)Order::query()->where([
                'agent_id' => $user->getId(),
                'price' => 0,
                'balance' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp()
            ])->get('sum(num)');

            $result['order']['fee'] = //random_int(0, 1000);
            (int)Order::query()->where([
                'agent_id' => $user->getId(),
                'price >' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ])->get('sum(num)');

            $result['commission']['total'] = //random_int(0, 10000) / 100;
             (int)CommissionBalance::query()->where([
                'openid' => $user->getOpenid(),
                'src' => [
                    CommissionBalance::ORDER_FREE,
                    CommissionBalance::ORDER_BALANCE,
                    CommissionBalance::ORDER_WX_PAY,
                    CommissionBalance::ORDER_REFUND,
                    CommissionBalance::GSP,
                    CommissionBalance::BONUS,
                ],
                'createtime <=' => $begin->getTimestamp(),
                'createtime >' => $end->getTimestamp(),
            ])->get('sum(x_val)');

            return $result;
        };

        $begin = self::parseMonth($month);
        if (!$begin) {
            return [];
        }

        return Util::cachedCall($begin->format('Y-m') === date('Y-m') ? 10 : 0, function () use ($fn, $begin, $detail) {
            $end = DateTimeImmutable::createFromMutable($begin)->modify('first day of next month 00:00');
            $result = [
                'summary' => $fn($begin, $end),
            ];

            if ($detail) {
                $result['list'] = [];
                while ($begin < $end) {
                    $start = DateTimeImmutable::createFromMutable($begin);
                    $begin->modify('next day 00:00');
                    $result['list'][$start->format('m月d日')] = $fn($start, $begin);
                }
            }

            return $result;
        }, $user->getId(), $begin->format('Y-m'), $detail);
    }
}
