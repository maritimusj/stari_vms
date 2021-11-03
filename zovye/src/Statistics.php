<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\model\deviceModelObj;

class Statistics
{
    public static function deviceOrderMonth(deviceModelObj $device, $month = '')
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
            return [];
        }
        
        $date->modify('first day of this month 00:00');
        $begin = DateTimeImmutable::createFromMutable($date);

        return Util::cachedCall($begin->format('Y-m') == date('Y-m') ? 10 : 0, function () use ($begin, $device) {
            $end = $begin->modify('+1 month');

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
}