<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use Exception;
use DateTimeImmutable;
use DateTimeInterface;
use zovye\base\modelObj;
use zovye\Contract\ISettings;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class Stats
{
    /**
     * 更新统计信息
     * @param orderModelObj $order
     * @param ISettings|ISettings[] $objs
     * @param callable|null $fn
     */
    public static function update(orderModelObj $order, $objs, callable $fn = null)
    {
        if ($objs) {
            $num = intval($order->getNum());

            if ($num > 0) {
                $objs = is_array($objs) ? $objs : [$objs];

                $way = $order->getPrice() > 0 ? 'p' : 'f';
                $createtime = $order->getCreatetime();

                $y = date('Y', $createtime); //年
                $n = date('n', $createtime); //月
                $z = date('z', $createtime); //一年中的第几天
                $j = date('j', $createtime); //月份中的第几天
                $G = date('G', $createtime); //小时

                foreach ($objs as $entry) {
                    if ($entry instanceof ISettings) {
                        $stats = $entry->get('statsData', []);

                        if (empty($stats['start']) || $createtime < $stats['start']) {
                            $stats['start'] = $createtime;
                        }
                        if ($createtime > $stats['end']) {
                            $stats['end'] = $createtime;
                        }

                        $stats['total'][$way] += $num;
                        $stats['data'][$y]['total'][$way] += $num;
                        $stats['data'][$y]['total'][$n][$way] += $num;

                        $stats['data'][$y]['days'][$n][$j][$way] += $num;
                        $stats['data'][$y]['hours'][$z][$G][$way] += $num;

                        if (is_callable($fn)) {
                            $fn($entry, $stats);
                        }

                        $entry->set('statsData', $stats);
                    }
                }
            }
        }
    }

    /**
     * 获取对象某天的统计数据
     * @param modelObj $obj
     * @param mixed $day
     * @return array
     */
    public static function getDayTotal(modelObj $obj, $day = null): array
    {
        try {
            if (is_string($day)) {
                $begin = new DateTime($day);
            } elseif ($day instanceof DateTimeInterface) {
                $begin = new DateTime($day->format('Y-m-d 00:00'));
            } else {
                $begin = new DateTime();
            }
        } catch (Exception $e) {
            return [];
        }

        $begin->modify('00:00');

        $first_order = Order::getFirstOrderOf($obj);
        if (!$first_order) {
            return [];
        }

        try {
            $order_date_obj = new DateTime(date('Y-m-d', $first_order['createtime']));
            if ($begin < $order_date_obj) {
                return [];
            }
        } catch (Exception $e) {
        }

        $counter = new OrderCounter();
        $result = $counter->getDayAll([$obj, 'goods'], $begin);

        self::calcBalanceOrder($result);

        return $result;
    }

    /**
     * 获取对象某月的统计数据
     * @param modelObj $obj
     * @param mixed $month
     * @return array
     */
    public static function getMonthTotal(modelObj $obj, $month = null): array
    {
        try {
            if (is_string($month)) {
                $begin = new DateTime($month);
            } elseif ($month instanceof DateTimeInterface) {
                $begin = new DateTime($month->format('Y-m-01 00:00'));
            } else {
                $begin = new DateTime();
            }
        } catch (Exception $e) {
            return [];
        }

        $begin->modify('first day of this month 00:00');

        $first_order = Order::getFirstOrderOf($obj);
        if (!$first_order) {
            return [];
        }

        try {
            $order_date_obj = new DateTime(date('Y-m-01', $first_order['createtime']));
            if ($begin < $order_date_obj) {
                return [];
            }
        } catch (Exception $e) {
        }

        $counter = new OrderCounter();
        $result = $counter->getMonthAll([$obj, 'goods'], $begin);

        self::calcBalanceOrder($result);

        return $result;
    }

    /**
     * 某取对象的总计数据
     * @param ISettings $obj
     * @return array
     */
    public static function total(ISettings $obj): array
    {
        $stats = $obj->get('statsData', []);
        $result = [
            'start' => $stats['start'],
            'end' => $stats['end'],
            'free' => intval($stats['total']['f']),
            'pay' => intval($stats['total']['p']),
        ];

        self::calcBalanceOrder($result);

        $result['total'] = $result['free'] + $result['pay'];

        return $result;
    }


    /**
     * 返回指定日期的日统计数据
     * @param modelObj $obj
     * @param mixed $day
     * @param string $title
     * @return array
     */
    public static function chartDataOfDay(modelObj $obj, DateTimeInterface $day, string $title = ''): array
    {
        $chart = self::getChartInitData($title);

        try {
            $first_order = Order::getFirstOrderOf($obj);
            if (!$first_order) {
                return $chart;
            }

            $begin = new DateTime($day->format('Y-m-d 00:00'));
            try {
                $order_date_obj = new DateTime(date('Y-m-d', $first_order['createtime']));
                if ($begin < $order_date_obj) {
                    return [];
                }
            } catch (Exception $e) {
            }

            $end = new DateTime($begin->format('Y-m-d 00:00'));
            $end->modify('next day');

            $counter = new OrderCounter();

            while ($begin < $end) {
                $data = $counter->getHourAll([$obj, 'goods'], $begin);

                self::calcBalanceOrder($data);

                $chart['series'][0]['data'][] = $data['free'];
                $chart['series'][1]['data'][] = $data['pay'];

                $chart['xAxis']['data'][] = $begin->format('H:00');

                $begin->modify('+1 hour');
            }

        } catch (Exception $e) {
        }

        return $chart;
    }

    /**
     * 返回指定月份的月统计数据
     * @param modelObj $obj
     * @param DateTimeInterface $month
     * @param string $title
     * @return array
     */
    public static function chartDataOfMonth(modelObj $obj, DateTimeInterface $month, string $title = ''): array
    {
        $chart = self::getChartInitData($title);

        try {
            $first_order = Order::getFirstOrderOf($obj);
            if (!$first_order) {
                return $chart;
            }

            $begin = new DateTime($month->format('Y-m-01 00:00'));

            try {
                $order_date_obj = new DateTime(date('Y-m-01', $first_order['createtime']));
                if ($begin < $order_date_obj) {
                    return [];
                }
            } catch (Exception $e) {
            }

            if ($begin->getTimestamp() < $first_order['createtime']) {
                $begin->setTimestamp($first_order['createtime']);
            }

            $end = new DateTime($month->format('Y-m-01'));
            $end->modify('first day of next month 00:00');
            $today = new DateTime();
            if ($end > $today) {
                $end = $today;
            }

            $counter = new OrderCounter();

            while ($begin < $end) {
                $data = $counter->getDayAll([$obj, 'goods'], $begin);

                self::calcBalanceOrder($data);

                $chart['series'][0]['data'][] = $data['free'];
                $chart['series'][1]['data'][] = $data['pay'];

                $chart['xAxis']['data'][] = $begin->format('m月d日');

                $begin->modify('+1 day');
            }

        } catch (Exception $e) {
        }

        return $chart;
    }

    private static function fillChartData($params = []): array
    {
        $chart = [
            'title' => ['text' => "{$params['title']}最近{$params['len']}日出货统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];

        $first_day = new DateTime("-{$params['len']} days 00:00");
        $last_day = new DateTime('next day 00:00');

        foreach ($params['data'] as $index => $item) {
            $chart['series'][$index] = [
                'type' => 'line',
                'smooth' => true,
                'stack' => '总量',
                'areaStyle' => ['normal' => []],
                'color' => Util::randColor(),
                'name' => $item['name'],
                'data' => [],
            ];

            try {
                $begin = new DateTime($first_day->format('Y-m-d 00:00'));
                while ($begin < $last_day) {
                    $data = Stats::getDayTotal($item['obj'], $begin);
                    $chart['series'][$index]['total'] += $data['total'];
                    $chart['series'][$index]['data'][] = $data['total'];
                    $begin->modify('next day');
                }
            } catch (Exception $e) {
            }
        }

        while ($first_day < $last_day) {
            $chart['xAxis']['data'][] = $first_day->format('m-d');
            $first_day->modify('next day');
        }

        foreach ($chart['series'] as $item) {
            $chart['legend']['data'][] = $item['name'];
        }

        return $chart;
    }

    /**
     * @param int $len
     * @param int $max
     * @return array
     */
    public static function chartDataOfAgents(int $len = 7, int $max = 15): array
    {
        $first_day = new DateTime("-$len days 00:00");

        $res = Order::query()
            ->where(['createtime >=' => $first_day->getTimestamp()])
            ->groupBy('agent_id')
            ->orderBy('total DESC')
            ->limit($max)
            ->getAll(['agent_id', 'SUM(num) AS total']);

        $data = [];

        if ($res) {
            foreach ($res as $item) {
                if (empty($item['agent_id'])) {
                    continue;
                }

                $agent = Agent::get($item['agent_id']);
                if (!$agent) {
                    continue;
                }

                $data[] = [
                    'obj' => $agent,
                    'name' => $agent->getName(),
                ];
            }
        }

        return self::fillChartData([
            'title' => '代理商',
            'len' => $len,
            'data' => $data,
        ]);
    }

    /**
     * @param int $len
     * @param int $max
     * @return array
     */
    public static function chartDataOfAccounts(int $len = 7, int $max = 15): array
    {
        $first_day = new DateTime("-$len days 00:00");

        $res = Order::query()
            ->where(['createtime >=' => $first_day->getTimestamp()])
            ->groupBy('account')
            ->orderBy('total DESC')
            ->limit($max)
            ->getAll(['account', 'SUM(num) AS total']);

        $data = [];

        if ($res) {
            foreach ($res as $item) {
                if (empty($item['account'])) {
                    continue;
                }

                $account = Account::findOneFromName($item['account']);
                if (empty($account)) {
                    continue;
                }

                $data[] = [
                    'obj' => $account,
                    'name' => $account->getTitle(),
                ];
            }
        }

        return self::fillChartData([
            'title' => '公众号',
            'len' => $len,
            'data' => $data,
        ]);
    }

    /**
     * @param int $len
     * @param int $max
     * @return array
     */
    public static function chartDataOfDevices(int $len = 7, int $max = 15): array
    {
        $first_day = new DateTime("-$len days 00:00");

        $res = Order::query()
            ->where(['createtime >=' => $first_day->getTimestamp()])
            ->groupBy('device_id')
            ->orderBy('total DESC')
            ->limit($max)
            ->getAll(['device_id', 'SUM(num) AS total']);

        $data = [];

        if ($res) {
            foreach ($res as $item) {
                if (empty($item['device_id'])) {
                    continue;
                }

                $device = Device::get($item['device_id']);
                if (empty($device)) {
                    continue;
                }

                $data[] = [
                    'obj' => $device,
                    'name' => $device->getName(),
                ];
            }
        }

        return self::fillChartData([
            'title' => '设备',
            'len' => $len,
            'data' => $data,
        ]);
    }

    /**
     * @return array
     */
    public static function brief(): array
    {
        $data = [
            'all' => [
                'n' => 0, //全部交易数量
                'f' => 0, //全部关注人数
            ],
            'today' => [
                'n' => 0, //今日交易数量,
                'f' => 0, //今日净增关注人数
            ],
            'yesterday' => [
                'n' => 0, //昨日交易数量,
                'f' => 0, //昨日净增关注人数
            ],
            'last7days' => [
                'n' => 0, //近7日交易数量
                'f' => 0, //近7日净增关注人数
            ],
            'month' => [
                'n' => 0, //本月交易数量
                'f' => 0, //本月净增关注人数
            ],
            'lastmonth' => [
                'n' => 0, //上月交易数量,
                'f' => 0, //上月净增关注人数
            ],
        ];

        $counter = new OrderCounter();
        $first_order = Order::getFirstOrder();
        $total = 0;
        if ($first_order) {
            $e = [app(), 'goods'];

            if (Config::app('order.total', 0) > 100000) {
                $last_order = Order::getLastOrder();
                if ($last_order) {
                    $total = $last_order['id'].'（订单）';
                }
            } else {
                $begin = new DateTime();
                $begin->setTimestamp($first_order['createtime']);
                $end = new DateTime();

                while ($begin < $end) {
                    $total += (int)$counter->getYearAll($e, $begin)['total'];
                    $begin->modify('+1 year');
                }
                Config::app('order.total', $total, true);
            }

            $data['all']['n'] = $total;
            $data['today']['n'] = (int)$counter->getDayAll($e, new DateTime('today'))['total'];
            $data['yesterday']['n'] = (int)$counter->getDayAll($e, new DateTime('yesterday 00:00'))['total'];

            $today = new DateTime('00:00');
            $last7days = new DateTime('-7 days 00:00');
            $total = 0;
            while ($today > $last7days) {
                $total += (int)$counter->getDayAll($e, $today)['total'];
                $today->modify('-1 day');
            }

            $data['last7days']['n'] = $total;

            $data['month']['n'] = (int)$counter->getMonthAll(
                $e,
                new DateTime('first day of this month 00:00')
            )['total'];
            $data['lastmonth']['n'] = (int)$counter->getMonthAll(
                $e,
                new DateTime('first day of last month 00:00')
            )['total'];
        }

        $query = User::query();

        $data['all']['f'] = $query->count();

        $today = new DateTime('today');
        $data['today']['f'] = $query->resetAll()->where([
            'createtime >=' => $today->getTimestamp(),
        ])->count();

        $data['yesterday']['f'] = Util::cachedCallUtil(
            new DateTime('next day 00:00:00'),
            function () use ($query, $today) {
                $yesterday = new DateTime('-1 day 00:00');

                return $query->resetAll()->where([
                    'createtime >=' => $yesterday->getTimestamp(),
                    'createtime <' => $today->getTimestamp(),
                ])->count();
            }
        );

        $data['last7days']['f'] = Util::cachedCallUtil(new DateTime('next day 00:00:00'), function () use ($query) {
            $last7days = new DateTime('-7 days 00:00');

            return $query->resetAll()->where([
                'createtime >=' => $last7days->getTimestamp(),
            ])->count();
        });

        $month = new DateTime('first day of this month 00:00');
        $data['month']['f'] = Util::cachedCallUtil(new DateTime('next day 00:00:00'), function () use ($query, $month) {
            return $query->resetAll()->where(['createtime >=' => $month->getTimestamp()])->count();
        });

        $data['lastmonth']['f'] = Util::cachedCallUtil(
            new DateTime('first day of next month 00:00:00'),
            function () use ($query, $month) {
                $last_month = new DateTime('first day of last month 00:00');

                return $query->resetAll()->where([
                    'createtime >=' => $last_month->getTimestamp(),
                    'createtime <' => $month->getTimestamp(),
                ])->count();
            }
        );

        $total = [
            'device' => Device::query()->count(),
            'agent' => Agent::query()->count(),
            'advs' => Account::query(['state' => 1])->count() + Advertising::query(['state <>' => Advertising::DELETED]
                )->count(),
            'user' => $data['all']['f'],
        ];

        return [
            'stats' => $data,
            'total' => $total,
        ];
    }


    public static function calc(orderModelObj $order, array &$stats)
    {
        $num = intval($order->getNum());
        if ($num > 0) {
            if ($order->isPay() || $order->getSrc() == Order::CHARGING) {
                $way = 'p';
            } elseif ($order->isFree()) {
                $way = 'f';
            } elseif ($order->getSrc() == Order::BALANCE) {
                $way = 'b';
            } else {
                $way = 'p';
            }

            $createtime = $order->getCreatetime();

            $G = date('G', $createtime); //小时
            $j = date('j', $createtime); //月份中的第几天
            $n = date('n', $createtime); //月
            $z = date('z', $createtime); //一年中的第几天
            $y = date('Y', $createtime); //年

            $stats['data'][$y]['days'][$n][$j][$way] += $num;
            $stats['data'][$y]['hours'][$z][$G][$way] += $num;
        }
    }

    /**
     * 修复 agentModelObj 或 deviceModelObj的某一天的统计数据
     * @param $obj
     * @param null $day
     * @return bool
     */
    public static function repair($obj, $day = null): bool
    {
        try {
            if (empty($day)) {
                $day = new DateTimeImmutable();
            } elseif (is_string($day)) {
                $day = new DateTimeImmutable($day);
            } elseif ($day instanceof DateTimeInterface) {
                $day = new DateTimeImmutable($day);
            } else {
                return false;
            }

            $begin = $day->modify('00:00');
            $end = $begin->modify('+1 day');

            $query = Order::query([
                'createtime >=' => $begin->getTimestamp(),
                'createtime <' => $end->getTimestamp(),
            ]);

            if ($obj instanceof agentModelObj) {
                $query->where(['agent_id' => $obj->getId()]);
            } elseif ($obj instanceof deviceModelObj) {
                $query->where(['device_id' => $obj->getId()]);
            } elseif ($obj instanceof accountModelObj) {
                $query->where(['account' => $obj->getName()]);
            } else {
                return false;
            }

            $stats = $obj->get('statsData', []);

            $y = $day->format('Y'); //年
            $n = $day->format('n'); //月
            $z = $day->format('z'); //一年中的第几天
            $j = $day->format('j'); //月份中的第几天

            unset($stats['data'][$y]['days'][$n][$j]);
            unset($stats['data'][$y]['hours'][$z]);

            foreach ($query->findAll() as $order) {
                self::calc($order, $stats);
            }

            unset($stats['data'][$y]['total'][$n]['p']);
            unset($stats['data'][$y]['total'][$n]['f']);
            unset($stats['data'][$y]['total'][$n]['b']);

            $p = 0;
            $f = 0;
            $b = 0;

            foreach ((array)$stats['data'][$y]['days'][$n] as $val) {
                $p += intval($val['p']);
                $f += intval($val['f']);
                $b += intval($val['b']);
            }

            $stats['data'][$y]['total'][$n]['p'] = $p;
            $stats['data'][$y]['total'][$n]['f'] = $f;
            $stats['data'][$y]['total'][$n]['b'] = $b;

            unset($stats['data'][$y]['total']['p']);
            unset($stats['data'][$y]['total']['f']);
            unset($stats['data'][$y]['total']['b']);

            $p = 0;
            $f = 0;
            $b = 0;

            foreach ((array)$stats['data'][$y]['total'] as $val) {
                $p += intval($val['p']);
                $f += intval($val['f']);
                $b += intval($val['b']);
            }

            $stats['data'][$y]['total']['p'] = $p;
            $stats['data'][$y]['total']['f'] = $f;
            $stats['data'][$y]['total']['b'] = $b;

            unset($stats['total']);

            $p = 0;
            $f = 0;
            $b = 0;

            foreach ((array)$stats['data'] as $val) {
                $p += intval($val['total']['p']);
                $f += intval($val['total']['f']);
                $b += intval($val['total']['b']);
            }

            $stats['total'] = [
                'p' => $p,
                'f' => $f,
                'b' => $b,
            ];

            return $obj->set('statsData', $stats);

        } catch (Exception $e) {
            Log::error('stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);
        }

        return false;
    }

    public static function repairMonthData($obj, $datetime = '')
    {
        $date = null;
        if (is_string($datetime)) {
            try {
                $date = new DateTimeImmutable($datetime);
            } catch (Exception $e) {
                return false;
            }
        } elseif ($datetime instanceof DateTimeInterface) {
            $date = $datetime;
        } elseif (is_int($datetime)) {
            $date = (new DateTimeImmutable())->setTimestamp($datetime);
        }

        if (!$date) {
            $date = new DateTimeImmutable();
        }

        $begin = $date->modify('first day of this month');
        $end = $date->modify('first day of next month');

        while ($begin < $end) {
            $day = $begin->format('Y-m-d');
            /** @var array $result */
            $result = Util::transactionDo(function () use ($obj, $day) {
                if (!Stats::repair($obj, $day)) {
                    return err('修复失败：{$day}！');
                }

                return true;
            });
            if (is_error($result)) {
                return $result;
            }
            $begin = $begin->modify('next day');
        }

        return true;
    }

    /**
     * @param modelObj $obj
     * @param mixed $day
     * @return array
     */
    public static function daysOfMonth(modelObj $obj, $day = null): array
    {
        if (is_string($day)) {
            try {
                $begin = new DateTime($day);
            } catch (Exception $e) {
                return [];
            }
        } else {
            $begin = new DateTime();
        }

        $begin->modify('first day of this month 00:00');

        $first_order = Order::getFirstOrderOf($obj);
        if (!$first_order) {
            return [];
        }

        try {
            $order_date_obj = new DateTime(date('Y-m-01', $first_order['createtime']));
            if ($begin < $order_date_obj) {
                return [];
            }
        } catch (Exception $e) {
        }

        try {
            $end = new DateTime($begin->format('Y-m-d 00:00:00'));
            $end->modify('first day of next month');
            if ($end->getTimestamp() > time()) {
                $end->setTimestamp(time());
            }
        } catch (Exception $e) {
            return [];
        }

        $result = [];
        $counter = new OrderCounter();
        while ($begin < $end) {
            $data = $counter->getDayAll([$obj, 'goods'], $begin);

            self::calcBalanceOrder($data);

            $result[$begin->format('m-d')] = [
                'free' => $data['free'],
                'fee' => $data['pay'],
                '_day' => $begin->format('d'),
            ];
            $begin->modify('next day');
        }

        uasort(
            $result,
            function ($a, $b) {
                return $b['_day'] - $a['_day'];
            }
        );

        return $result;
    }


    /**
     * @param modelObj $obj
     * @param mixed $day
     * @return array
     */
    public static function hoursOfDay(modelObj $obj, $day = null): array
    {
        try {
            if (is_string($day)) {
                $begin = new DateTime($day);
            } else {
                $begin = new DateTime();
            }
        } catch (Exception $e) {
            return [];
        }

        $begin->modify('00:00:00');

        $first_order = Order::getFirstOrderOf($obj);
        if (!$first_order) {
            return [];
        }

        try {
            $order_date_obj = new DateTime(date('Y-m-d', $first_order['createtime']));
            if ($begin < $order_date_obj) {
                return [];
            }
        } catch (Exception $e) {
        }

        try {
            $end = new DateTime($begin->format('Y-m-d 00:00'));
            $end->modify('next day 00:00');
            if ($end->getTimestamp() > time()) {
                $end->setTimestamp(time());
            }
        } catch (Exception $e) {
            return [];
        }

        $result = [];
        $counter = new OrderCounter();
        while ($begin < $end) {
            $data = $counter->getHourAll([$obj, 'goods'], $begin);

            self::calcBalanceOrder($data);

            $result[intval($begin->format('H'))] = [
                'free' => $data['free'],
                'fee' => $data['pay'],
            ];

            $begin->modify('+1 hour');
        }

        return $result;
    }

    /**
     * @param string $title
     * @return array
     */
    public static function getChartInitData(string $title): array
    {
        $chart = [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['data' => ['免费', '支付'], 'bottom' => 0],
            'xAxis' => ['type' => 'category'],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'series' => [
                [
                    'type' => 'line',
                    'color' => '#00CC33',
                    'name' => '免费',
                    'data' => [],
                ],
                [
                    'type' => 'line',
                    'color' => '#FF3300',
                    'name' => '支付',
                    'data' => [],
                ],
            ],
        ];

        if ($title) {
            $chart['title'] = ['text' => $title];
        }

        return $chart;
    }

    private static function calcBalanceOrder(array &$data)
    {
        if (App::isBalanceEnabled()) {
            if (Balance::isFreeOrder()) {
                $data['free'] += $data['balance'];
            } elseif (Balance::isPayOrder()) {
                $data['pay'] += $data['balance'];
            }
        }
    }

    public static function getUserCommissionStats(userModelObj $user): array
    {
        list($years, $result) = self::getUserMonthCommissionStatsOfYear($user, '');
        array_shift($years);

        foreach ($years as $year) {
            list(, $data) = self::getUserMonthCommissionStatsOfYear($user, $year);
            $result = array_merge($result, $data);
        }

        ksort($result);

        $last_month_balance = 0;
        foreach ($result as $key => $item) {
            $result[$key]['balance'] = $item['income'] + $item['withdraw'] + $item['fee'] + $last_month_balance;
            $last_month_balance = $result[$key]['balance'];
        }

        krsort($result);

        return $result;
    }

    public static function getUserMonthCommissionStatsOfYear(userModelObj $user, $year): array
    {
        $result = [[], []];
        $first = CommissionBalance::getFirstCommissionBalance($user);
        if (empty($first)) {
            return $result;
        }

        $first_datetime = new DateTime("@{$first->getCreatetime()}");

        try {
            if (empty($year)) {
                $time = $first_datetime;
            } elseif (is_string($year)) {
                $time = new DateTime("$year-01-01 00:00");
            } elseif ($year instanceof DateTimeInterface) {
                $time = new DateTime($year->format('Y-01-01 00:00'));
            } else {
                return $result;
            }

            if ($time < $first_datetime) {
                if ($time->format('Y') == $first_datetime->format('Y')) {
                    $time = $first_datetime;
                } else {
                    return $result;
                }
            }

            $begin = new DateTime($time->format('Y-m-d 00:00'));
            $begin->modify('first day of this month');

            $end = new DateTime($time->format('Y-m-d 00:00'));
            $end->modify('first day of jan next year 00:00');

        } catch (Exception $e) {
            return $result;
        }

        $now = new DateTime();
        if ($end > $now) {
            $end = $now;
        }

        $data = [];
        $years = [];

        $first_datetime->modify('first day of jan');
        while ($first_datetime < $now) {
            $years[] = $first_datetime->format('Y');
            $first_datetime->modify('next year');
        }

        $now_str = $now->format('Y年m月');

        while ($begin < $end) {
            $month_str = $begin->format('Y年m月');

            $uid = Cache::makeUID([
                'api' => 'monthStats',
                'user' => $user->getOpenid(),
                'month' => $month_str,
            ]);

            $params = [];

            if ($month_str == $now_str) {
                $params[] = Cache::ResultExpiredAfter(10);
            }

            $res = Cache::fetch($uid, function () use ($user, $begin) {
                return Stats::getMonthCommissionStatsData($user, $begin);
            }, ...$params);

            if (is_error($res)) {
                return $res;
            }

            $data[$month_str] = $res;

            $begin->modify('next month');
        }

        krsort($data);

        $result[0] = $years;
        $result[1] = $data;

        return $result;
    }

    public static function getMonthCommissionStatsData(userModelObj $user, $month): array
    {
        $cond = [
            'openid' => $user->getOpenid(),
        ];

        try {
            if (is_string($month)) {
                $time = new DateTime($month);
            } elseif ($month instanceof DateTimeInterface) {
                $time = new DateTime($month->format('Y-m-d 00:00'));
            } else {
                $time = new DateTime();
            }
            $time->modify('first day of this month 00:00');
            $cond['createtime >='] = $time->getTimestamp();

            $time->modify('first day of next month 00:00');
            $cond['createtime <'] = $time->getTimestamp();

        } catch (Exception $e) {
            return [];
        }

        $res = CommissionBalance::query($cond)->findAll();

        $c_arr = [
            CommissionBalance::ORDER_FREE,
            CommissionBalance::ORDER_BALANCE,
            CommissionBalance::ORDER_WX_PAY,
            CommissionBalance::ORDER_REFUND,
            CommissionBalance::REFUND,
            CommissionBalance::GSP,
            CommissionBalance::BONUS,
        ];

        $data = [
            'income' => 0,
            'withdraw' => 0,
            'fee' => 0,
        ];
        /** @var commission_balanceModelObj $item */
        foreach ($res as $item) {

            $src = $item->getSrc();
            $x_val = $item->getXVal();

            if (in_array($src, $c_arr)) {

                $data['income'] += $x_val;

            } elseif ($src == CommissionBalance::ADJUST) {

                if ($x_val > 0) {
                    $data['income'] += $x_val;
                } else {
                    $data['withdraw'] += $x_val;
                }

            } elseif ($src == CommissionBalance::WITHDRAW) {

                $data['withdraw'] += $x_val;

            } elseif ($src == CommissionBalance::FEE) {

                $data['fee'] += $x_val;

            } else {
                if ($x_val > 0) {
                    $data['income'] += $x_val;
                } else {
                    $data['fee'] += $x_val;
                }
            }
        }

        return $data;
    }
}
