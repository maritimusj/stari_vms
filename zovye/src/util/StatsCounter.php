<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\util;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\domain\Counter;
use zovye\domain\Locker;
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

                if (intval($hour->format('YmdH')) < intval(date('YmdH')) && Locker::try("counter:init:$uid")) {
                    Counter::create([
                        'uid' => $uid,
                        'num' => $v,
                        'createtime' => time(),
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
                $begin = new DateTimeImmutable($day->format('Y-m-d 00:00'));
                $end = $begin->modify('next day 00:00');

                $num = $this->initFN($begin, $end, $params);

                if (intval($day->format('Ymd')) < intval(date('Ymd')) && Locker::try("counter:init:$uid")) {
                    Counter::create([
                        'uid' => $uid,
                        'num' => $num,
                        'createtime' => time(),
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
                $begin = new DateTimeImmutable($month->format('Y-m-01 00:00'));
                $end = $begin->modify("first day of next month 00:00");

                $num = $this->initFN($begin, $end, $params);

                if (intval($month->format('Ym')) < intval(date('Ym')) && Locker::try("counter:init:$uid")) {
                    Counter::create([
                        'uid' => $uid,
                        'num' => $num,
                        'createtime' => time(),
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
            $begin = new DateTimeImmutable($year->format('Y-01-01 00:00'));
            $end = $begin->modify("first day of Jan next year 00:00");

            $num = $this->initFN($begin, $end, $params);

            if (intval($year->format('Y')) < intval(date('Y')) && Locker::try("counter:init:$uid")) {
                Counter::create([
                    'uid' => $uid,
                    'num' => $num,
                    'createtime' => time(),
                ]);
            }

            return $num;

        } catch (Exception $e) {
        }

        return 0;
    }
}