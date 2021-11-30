<?php

namespace zovye;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\model\counterModelObj;

abstract class StatsCounter
{
    abstract protected function makeUID(array $params = []): string;

    abstract protected function initFN(DateTimeInterface $begin, DateTimeInterface $end, array $params = []);

    public function getHour(DateTimeInterface $time, array $params = []): int
    {
        $uid = $this->makeUID(array_merge(['datetime' => $time->format('YmdH')], $params));

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        try {
            $begin = new DateTimeImmutable($time->format('Y-m-d H:00:00'));
            $end = $begin->modify('+1 hour');

            $v = $this->initFN($begin, $end, $params);

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

    public function getDay(DateTimeInterface $time, $params = []): int
    {
        $uid = $this->makeUID(array_merge(['datetime' => $time->format('Ymd')], $params));

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
                $total += self::getHour($begin, $params);
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

    public function getMonth(DateTimeInterface $time, $params = []): int
    {
        $uid = $this->makeUID(array_merge(['datetime' => $time->format('Ym')], $params));

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
                $total += self::getDay($begin, $params);
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

    public function getYear(DateTimeInterface $time, $params = []): int
    {
        $uid = $this->makeUID(array_merge(['datetime' => $time->format('Y')], $params));

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
                $total += self::getMonth($begin, $params);
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
}