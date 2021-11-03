<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\model\agentModelObj;
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

    public static function userOrderMonth(userModelObj $user, $month = '', $detail = false)
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

            $result['order']['free'] = (int)Order::query()->where([
                'agent_id' => $user->getId(),
                'price' => 0,
                'balance' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp()
            ])->get('sum(num)');

            $result['order']['fee'] = (int)Order::query()->where([
                'agent_id' => $user->getId(),
                'price >' => 0,
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ])->get('sum(num)');

            $result['commission']['total'] = (int)CommissionBalance::query()->where([
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
                'brief' => $fn($begin, $end),
            ];

            if ($detail) {
                $result['list'] = [];
                while ($begin < $end) {
                    $start = DateTimeImmutable::createFromMutable($begin);
                    $begin->modify('next day 00:00');
                    $result['list'][$start->format('Y-m-d')] = $fn($start, $begin);
                }
            }

            return $result;
        });
    }
}