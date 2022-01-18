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
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;

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
        if (is_string($day)) {
            try {
                $begin = new DateTime($day);
            } catch (Exception $e) {
                return [];
            }
        } elseif ($day instanceof DateTimeInterface) {
            $begin = new DateTime($day->format('Y-m-d 00:00'));
        } else {
            $begin = new DateTime();
        }

        $first_order = Order::getFirstOrderOf($obj);
        if (!$first_order) {
            return [];
        }

        $begin->modify('00:00');
        if ($begin->getTimestamp() < $first_order['createtime']) {
            return [];
        }

        $counter = new OrderCounter();
        $result = $counter->getDayAll([$obj, 'goods'], $begin);

        if (App::isBalanceEnabled()) {
            if (Balance::isPayOrder()) {
                $result['pay'] += intval($result['balance']);
            } elseif (Balance::isFreeOrder()) {
                $result['free'] += intval($result['balance']);
            }
        }

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
        if (is_string($month)) {
            try {
                $begin = new DateTime($month);
            } catch (Exception $e) {
                return [];
            }
        } elseif ($month instanceof DateTimeInterface) {
            $begin = new DateTime($month->format('Y-m-01 00:00'));
        } else {
            $begin = new DateTime();
        }

        $first_order = Order::getFirstOrderOf($obj);
        if (!$first_order) {
            return [];
        }

        $begin->modify('first day of this month 00:00');
        if ($begin->getTimestamp() < $first_order['createtime']) {
            return [];
        }

        $counter = new OrderCounter();
        $result = $counter->getMonthAll([$obj, 'goods'], $begin);

        if (App::isBalanceEnabled()) {
            if (Balance::isPayOrder()) {
                $result['pay'] += intval($result['balance']);
            } elseif (Balance::isFreeOrder()) {
                $result['free'] += intval($result['balance']);
            }
        }

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

        if (App::isBalanceEnabled()) {
            if (Balance::isPayOrder()) {
                $result['pay'] += intval($stats['b']);
            } elseif (Balance::isFreeOrder()) {
                $result['free'] += intval($stats['b']);
            }
        }

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

        try {
            $begin = new DateTime($day->format('Y-m-d 00:00'));
            $first_order = Order::getFirstOrderOf($obj);
            if (!$first_order || $begin->getTimestamp() < $first_order['createtime']) {
                return $chart;
            }

            $end = new DateTime($begin->format('Y-m-d 00:00'));
            $end->modify('next day');

            $counter = new OrderCounter();

            while ($begin < $end) {
                $data = $counter->getHourAll([$obj, 'goods'], $begin);
                if (App::isBalanceEnabled()) {
                    if (Balance::isFreeOrder()) {
                        $data['free'] += $data['balance'];
                    } elseif (Balance::isPayOrder()) {
                        $data['pay'] += $data['balance'];
                    }
                }

                $chart['series'][0]['data'][] = $data['free'];
                $chart['series'][1]['data'][] = $data['pay'];

                $chart['xAxis']['data'][] = $begin->format('i:00');

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

        try {
            $begin = new DateTime($month->format('Y-m-01 00:00'));

            $first_order = Order::getFirstOrderOf($obj);
            if (!$first_order || $begin->getTimestamp() < $first_order['createtime']) {
                return $chart;
            }

            $end = new DateTime($month->format('Y-m-01'));
            $end->modify('first day of next month 00:00');

            $counter = new OrderCounter();

            while ($begin < $end) {
                $data = $counter->getDayAll([$obj, 'goods'], $begin);
                if (App::isBalanceEnabled()) {
                    if (Balance::isFreeOrder()) {
                        $data['free'] += $data['balance'];
                    } elseif (Balance::isPayOrder()) {
                        $data['pay'] += $data['balance'];
                    }
                }

                $chart['series'][0]['data'][] = $data['free'];
                $chart['series'][1]['data'][] = $data['pay'];

                $chart['xAxis']['data'][] = $begin->format('m月d日');

                $begin->modify('+1 day');
            }

        } catch (Exception $e) {
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
        $chart = [
            'title' => ['text' => "代理商最近{$len}日出货统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];

        $first_day = new DateTime("-$len days 00:00");
        $last_day = new DateTime('next day 00:00');

        $stats = Order::query()
            ->where(['createtime >=' => $first_day->getTimestamp()])
            ->groupBy('agent_id')
            ->orderBy('total ASC')
            ->limit($max)
            ->getAll(['agent_id', 'count(*) AS total']);

        foreach ($stats as $index => $stat) {
            if (empty($stat['agent_id'])) {
                continue;
            }
            $agent = Agent::get($stat['agent_id']);
            if (!$agent) {
                continue;
            }
            $chart['series'][$index] = [
                'type' => 'line',
                'smooth' => true,
                'stack' => '总量',
                'areaStyle' => ['normal' => []],
                'color' => Util::randColor(),
                'name' => $agent->getName(),
                'data' => [],
            ];
            try {
                $begin = new DateTime($first_day->format('Y-m-d 00:00'));
                while ($begin < $last_day) {
                    $data = Stats::getDayTotal($agent, $begin);
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

        $chart['series'] = array_slice($chart['series'], 0, $max);
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
    public static function chartDataOfAccounts(int $len = 7, int $max = 15): array
    {
        $chart = [
            'title' => ['text' => "公众号最近{$len}日出货统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];


        $first_day = new DateTime("-$len days 00:00");
        $last_day = new DateTime('next day 00:00');

        $stats = Order::query()
            ->where(['createtime >=' => $first_day->getTimestamp()])
            ->groupBy('account')
            ->orderBy('total ASC')
            ->limit($max)
            ->getAll(['account', 'count(*) AS total']);

        foreach ($stats as $index => $stat) {
            if (empty($stat['account'])) {
                continue;
            }

            $account = Account::findOneFromName($stat['account']);
            if (empty($account)) {
                continue;
            }

            $chart['series'][$index] = [
                'type' => 'line',
                'smooth' => true,
                'stack' => '总量',
                'areaStyle' => ['normal' => []],
                'color' => Util::randColor(),
                'name' => $account->getTitle(),
                'data' => [],
            ];

            try {
                $begin = new DateTime($first_day->format('Y-m-d 00:00'));
                while ($begin < $last_day) {
                    $data = Stats::getDayTotal($account, $begin);
                    $chart['series'][$index]['total'] += $data['total'];
                    $chart['series'][$index]['data'][] = $data['total'];
                    $begin->modify('next day');
                }
            } catch (Exception $e) {
            }
        }

        $chart['series'] = array_slice($chart['series'], 0, $max);
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
    public static function chartDataOfDevices(int $len = 7, int $max = 15): array
    {
        $chart = [
            'title' => ['text' => "设备最近{$len}日出货统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];

        $first_day = new DateTime("-$len days 00:00");
        $last_day = new DateTime('next day 00:00');

        $stats = Order::query()
            ->where(['createtime >=' => $first_day->getTimestamp()])
            ->groupBy('device_id')
            ->orderBy('total ASC')
            ->limit($max)
            ->getAll(['device_id', 'count(*) AS total']);

        foreach ($stats as $index => $stat) {
            if (empty($stat['device_id'])) {
                continue;
            }

            $device = Device::get($stat['device_id']);
            if (empty($device)) {
                continue;
            }

            $chart['series'][$index] = [
                'type' => 'line',
                'smooth' => true,
                'stack' => '总量',
                'areaStyle' => ['normal' => []],
                'color' => Util::randColor(),
                'name' => $device->getName(),
                'data' => [],
            ];

            try {
                $begin = new DateTime($first_day->format('Y-m-d 00:00'));
                while ($begin < $last_day) {
                    $data = Stats::getDayTotal($device, $begin);
                    $chart['series'][$index]['total'] += $data['total'];
                    $chart['series'][$index]['data'][] = $data['total'];
                    $begin->modify('next day');
                }
            } catch (Exception $e) {
            }
        }

        $chart['series'] = array_slice($chart['series'], 0, $max);
        foreach ($chart['series'] as $item) {
            $chart['legend']['data'][] = $item['name'];
        }

        return $chart;
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

            $begin = new DateTime();
            $begin->setTimestamp($first_order['createtime']);
            $end = new DateTime();
            
            while($begin < $end) {
                $total += (int)$counter->getYearAll($e, $begin)['total'];
                $begin->modify('+1 year');
            }

            $data['all']['n'] = $total;
            $data['today']['n'] = (int)$counter->getDayAll($e, new DateTime('today'))['total'];
            $data['yesterday']['n'] = (int)$counter->getDayAll($e, new DateTime('yesterday 00:00'))['total'];

            $today = new DateTime('today');
            $last7days = new DateTime('-7 days 00:00');
            $total = 0;
            while($today > $last7days) {
                $total += (int)$counter->getDayAll($e, $today)['total'];
                $today->modify('-1 day');
            }

            $data['last7days']['n'] = $total;
            
            $data['month']['n'] = (int)$counter->getMonthAll($e, new DateTime('first day of this month 00:00'))['total']; 
            $data['lastmonth']['n'] = (int)$counter->getMonthAll($e, new DateTime('first day of last month 00:00'))['total']; 
        }

        $query = User::query();

        $data['all']['f'] = $query->count();

        $today = new DateTime('today');
        $data['today']['f'] = $query->resetAll()->where([
            'createtime >=' => $today->getTimestamp(),
        ])->count();

        $data['yesterday']['f'] = Util::cachedCallUtil(new DateTime('next day 00:00:00'), function () use ($query, $today) {
            $yesterday = new DateTime('-1 day 00:00');
            return $query->resetAll()->where([
                'createtime >=' => $yesterday->getTimestamp(),
                'createtime <' => $today->getTimestamp(),
            ])->count();
        });

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

        $data['lastmonth']['f'] = Util::cachedCallUtil(new DateTime('first day of next month 00:00:00'), function () use ($query, $month) {
            $lastmonth = new DateTime('first day of last month 00:00');
            return $query->resetAll()->where([
                'createtime >=' => $lastmonth->getTimestamp(), 
                'createtime <' => $month->getTimestamp()
            ])->count();
        });

        $total = [
            'device' => Device::query()->count(),
            'agent' => Agent::query()->count(),
            'advs' => Account::query(['state' => 1])->count() + Advertising::query(['state <>' => Advertising::DELETED])->count(),
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
            if ($order->getSrc() == Order::PAY) {
                $way = 'p';
            } elseif ($order->getSrc() == Order::ACCOUNT) {
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
        if (empty($day)) {
            $day = time();
        } elseif (is_string($day)) {
            $day = strtotime($day);
        } elseif ($day instanceof DateTimeInterface) {
            $day = $day->getTimestamp();
        }

        try {
            $begin = new DateTimeImmutable(date('Y-m-d', $day));

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

            $y = date('Y', $day); //年
            $n = date('n', $day); //月
            $z = date('z', $day); //一年中的第几天
            $j = date('j', $day); //月份中的第几天

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
        if (empty($first_order) || $begin->getTimestamp() < $first_order['createtime']) {
            return [];
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

            if (App::isBalanceEnabled()) {
                if (Balance::isFreeOrder()) {
                    $data['free'] += intval($data['balance']);
                } elseif (Balance::isPayOrder()) {
                    $data['pay'] += intval($data['balance']);
                }
            }

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
        if (is_string($day)) {
            try {
                $begin = new DateTime($day);
            } catch (Exception $e) {
                return [];
            }
        } else {
            $begin = new DateTime();
        }

        $begin->modify('00:00:00');

        $first_order = Order::getFirstOrderOf($obj);
        if (empty($first_order) || $begin->getTimestamp() < $first_order['createtime']) {
            return [];
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

            if (App::isBalanceEnabled()) {
                if (Balance::isFreeOrder()) {
                    $data['free'] += intval($data['balance']);
                } elseif (Balance::isPayOrder()) {
                    $data['pay'] += intval($data['balance']);
                }
            }

            $result[intval($begin->format('H'))] = [
                'free' => $data['free'],
                'fee' => $data['pay'],
            ];
            $begin->modify('+1 hour');
        }

        return $result;
    }
}
