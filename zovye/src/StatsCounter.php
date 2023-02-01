<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

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

    public function getHour(array $params, DateTimeInterface...$hours): int
    {
        $total = 0;

        foreach ($hours as $hour) {
            $uid = $this->makeUID(array_merge(['datetime' => $hour->format('YmdH')], $params));

            /** @var counterModelObj $counter */
            $counter = Counter::get($uid, true);
            if ($counter) {
                $total += $counter->getNum();
                continue;
            }

            try {
                $begin = new DateTimeImmutable($hour->format('Y-m-d H:00:00'));
                $end = $begin->modify('+1 hour');

                $v = $this->initFN($begin, $end, $params);

                if ((int)$hour->format('YmdH') < (int)date('YmdH') && Locker::try("counter:init:$uid")) {
                    Counter::create([
                        'uid' => $uid,
                        'num' => $v,
                        'createtime' => time(),
                        'updatetime' => 0,
                    ]);
                }

                $total += $v;

            } catch (Exception $e) {
            }
        }

        return $total;
    }

    public function getDay(array $params = [], DateTimeInterface...$days): int
    {
        $total = 0;

        foreach ($days as $day) {
            $uid = $this->makeUID(array_merge(['datetime' => $day->format('Ymd')], $params));

            /** @var counterModelObj $counter */
            $counter = Counter::get($uid, true);
            if ($counter) {
                $total += $counter->getNum();
                continue;
            }

            try {
                $begin = new DateTime($day->format('Y-m-d 00:00'));
                $end = new DateTime($day->format('Y-m-d 00:00'));

                $end->modify('next day 00:00');

                $num = $this->initFN($begin, $end, $params);

                if ((int)$day->format('Ymd') < (int)date('Ymd') && Locker::try("counter:init:$uid")) {
                    Counter::create([
                        'uid' => $uid,
                        'num' => $num,
                        'createtime' => time(),
                        'updatetime' => 0,
                    ]);
                }

                $total += $num;

            } catch (Exception $e) {
            }
        }

        return $total;
    }

    public function getMonth(array $params, DateTimeInterface...$months): int
    {
        $total = 0;

        foreach ($months as $month) {
            $uid = $this->makeUID(array_merge(['datetime' => $month->format('Ym')], $params));

            /** @var counterModelObj $counter */
            $counter = Counter::get($uid, true);
            if ($counter) {
                $total += $counter->getNum();
                continue;
            }

            try {
                $begin = new DateTime($month->format('Y-m 00:00'));
                $end = new DateTime($month->format('Y-m 00:00'));

                $end->modify("first day of next month 00:00");

                $num = $this->initFN($begin, $end, $params);

                if ((int)$month->format('Ym') < (int)date('Ym') && Locker::try("counter:init:$uid")) {
                    Counter::create([
                        'uid' => $uid,
                        'num' => $num,
                        'createtime' => time(),
                        'updatetime' => 0,
                    ]);
                }

                $total += $num;

            } catch (Exception $e) {
            }
        }

        return $total;
    }

    public function getYear(array $params, DateTimeInterface $year): int
    {
        $uid = $this->makeUID(array_merge(['datetime' => $year->format('Y')], $params));

        /** @var counterModelObj $counter */
        $counter = Counter::get($uid, true);
        if ($counter) {
            return $counter->getNum();
        }

        try {
            $begin = new DateTime($year->format('Y-01-01 00:00'));
            $end = new DateTime($year->format('Y-01-01 00:00'));

            $end->modify("first day of Jan next year 00:00");

            $num = $this->initFN($begin, $end, $params);

            if ((int)$year->format('Y') < (int)date('Y') && Locker::try("counter:init:$uid")) {
                Counter::create([
                    'uid' => $uid,
                    'num' => $num,
                    'createtime' => time(),
                    'updatetime' => 0,
                ]);
            }

            return $num;

        } catch (Exception $e) {
        }

        return 0;
    }

    public function removeDays(array $params = [], DateTimeInterface...$days)
    {
        foreach ($days as $day) {
            $uid = $this->makeUID(array_merge(['datetime' => $day->format('Ymd')], $params));
            $counter = Counter::get($uid, true);
            if ($counter && Locker::try("counter:init:$uid")) {
                $counter->destroy();
            }
        }
    }

    public function removeMonths(array $params, DateTimeInterface...$months)
    {
        foreach ($months as $month) {
            $uid = $this->makeUID(array_merge(['datetime' => $month->format('Ym')], $params));
            $counter = Counter::get($uid, true);
            if ($counter && Locker::try("counter:init:$uid")) {
                $counter->destroy();
            }
        }
    }

    public function removeYears(array $params, DateTimeInterface...$years)
    {
        foreach ($years as $year) {
            $uid = $this->makeUID(array_merge(['datetime' => $year->format('Y')], $params));
            $counter = Counter::get($uid, true);
            if ($counter && Locker::try("counter:init:$uid")) {
                $counter->destroy();
            }
        }
    }
}