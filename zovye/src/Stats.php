<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use Exception;
use DateTimeImmutable;
use DateTimeInterface;
use zovye\Contract\ISettings;
use zovye\model\agentModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;

class Stats
{
    /**
     * 更新统计信息
     * @param orderModelObj $order
     * @param ISettings|ISettings[] $objs
     * @param callable $fn
     */
    public static function update(orderModelObj $order, $objs, $fn = null)
    {
        if ($objs && $order) {
            $num = intval($order->getNum());

            if ($num > 0) {
                $objs = is_array($objs) ? $objs : [$objs];

                $way = $order->getPrice() > 0 ? 'p' : ($order->getBalance() > 0 ? 'b' : 'f');
                $createtime = $order->getCreatetime();

                $y = date('Y', $createtime); //年
                $n = date('n', $createtime); //月
                $z = date('z', $createtime); //一年中的第几天
                $j = date('j', $createtime); //月份中的第几天
                $G = date('G', $createtime); //小时

                foreach ($objs as $entry) {
                    if ($entry instanceof ISettings) {
                        $stats = $entry->get('statsData', []);

                        //只保存今年的数据
                        //$stats = ['total' => $stats['total'] , date('Y') => $stats[date('Y')]);

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
     * @param ISettings $obj
     * @param null|string $day
     * @return array
     */
    public static function getDayTotal(ISettings $obj, $day = null): array
    {
        if (empty($day)) {
            $day = time();
        } elseif (is_string($day)) {
            $day = strtotime($day);
        }

        $y = date('Y', $day); //年
        $n = date('n', $day); //月
        $j = date('j', $day); //月份中的第几天

        $stats = $obj->get('statsData', [])['data'][$y]['days'][$n][$j];
        $result = [
            'fee' => intval($stats['p']),
            'free' => intval($stats['f']),
            'balance' => intval($stats['b']),
        ];

        $result['total'] = $result['fee'] + $result['free'] + $result['balance'];

        return $result;
    }

    /**
     * 获取对象某月的统计数据
     * @param ISettings $obj
     * @param null|string $month
     * @return array
     */
    public static function getMonthTotal(ISettings $obj, $month = null): array
    {
        if (empty($month)) {
            $month = time();
        } elseif (is_string($month)) {
            $month = strtotime($month);
        }

        $y = date('Y', $month); //年
        $n = date('n', $month); //月

        $stats = $obj->get('statsData', [])['data'][$y]['total'][$n];
        $result = [
            'fee' => intval($stats['p']),
            'free' => intval($stats['f']),
            'balance' => intval($stats['b']),
        ];

        $result['total'] = $result['fee'] + $result['free'] + $result['balance'];

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
            'fee' => intval($stats['total']['p']),
            'balance' => intval($stats['total']['b']),
        ];
        $result['total'] = $result['free'] + $result['fee'] + $result['balance'];

        return $result;
    }

    /**
     * 按月获取对象的统计数据
     * @param ISettings $obj
     * @param null|string $year
     * @return array
     */
    public static function months(ISettings $obj, $year = null): array
    {
        if (empty($year)) {
            $year = time();
        } elseif (is_string($year)) {
            $year = strtotime($year);
        }

        $stats = $obj->get('statsData', []);

        $y = date('Y', $year); //年

        $data = $stats['data'][$y]['total'] ?: [];

        if ($data) {
            unset($data['f'], $data['b'], $data['p']);

            uksort(
                $data,
                function ($a, $b) {
                    return $b - $a;
                }
            );
        }

        return $data;
    }

    /**
     * 返回指定日期的日统计数据
     * @param ISettings $obj
     * @param mixed $day
     * @param string $title
     * @return array
     */
    public static function chartDataOfDay(ISettings $obj, $day, $title = ''): array
    {
        if (empty($day)) {
            $day = time();
        } elseif (is_string($day)) {
            $day = strtotime($day);
        } elseif ($day instanceof DateTimeInterface) {
            $day = $day->getTimestamp();
        }

        $chart = [];

        $stats = $obj->get('statsData', [])['data'][date('Y', $day)]['hours'][date('z', $day)];
        if ($stats) {
            $chart = [
                'tooltip' => ['trigger' => 'axis'],
                'legend' => ['data' => ['免费', '余额', '支付'], 'bottom' => 0],
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
                    [
                        'type' => 'line',
                        'color' => '#3399FF',
                        'name' => '余额',
                        'data' => [],
                    ],
                ],
            ];

            if ($title) {
                $chart['title'] = ['text' => $title];
            }

            ksort($stats);

            $i = key($stats);
            $g = date('G', $day);
            for (; $i <= $g; $i++) {
                $chart['xAxis']['data'][] = "{$i}:00";
                $chart['series'][0]['data'][] = intval($stats[$i]['f']);
                $chart['series'][1]['data'][] = intval($stats[$i]['p']);
                $chart['series'][2]['data'][] = intval($stats[$i]['b']);
            }
        }

        return $chart ?: [];
    }

    /**
     * 返回指定月份的月统计数据
     * @param ISettings $obj
     * @param $day
     * @param string $title
     * @return array
     */
    public static function chartDataOfMonth(ISettings $obj, $day, $title = ''): array
    {
        if (empty($day)) {
            $day = time();
        } elseif (is_string($day)) {
            $day = strtotime($day);
        } elseif ($day instanceof DateTimeInterface) {
            $day = $day->getTimestamp();
        }

        $chart = [];
        $stats = $obj->get('statsData', [])['data'][date('Y', $day)]['days'][date('n', $day)];
        if ($stats) {
            $month = date('n月', $day);
            $chart = [
                'tooltip' => ['trigger' => 'axis'],
                'legend' => ['data' => ['免费', '余额', '支付'], 'bottom' => 0],
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
                    [
                        'type' => 'line',
                        'color' => '#3399FF',
                        'name' => '余额',
                        'data' => [],
                    ],
                ],
            ];
            if ($title) {
                $chart['title'] = ['text' => $title];
            }

            ksort($stats);

            $i = key($stats) ?: 1;
            $keys = array_keys($stats);
            $end = end($keys);

            for (; $i <= $end; $i++) {
                $chart['series'][0]['data'][] = intval($stats[$i]['f']);
                $chart['series'][1]['data'][] = intval($stats[$i]['p']);
                $chart['series'][2]['data'][] = intval($stats[$i]['b']);
                $chart['xAxis']['data'][] = "{$month}{$i}日";
            }
        }

        return $chart ?: [];
    }

    /**
     * @param int $len
     * @param int $max
     * @return array
     */
    public static function chartDataOfAgents($len = 7, $max = 15): array
    {
        $chart = [
            'title' => ['text' => "代理商最近{$len}日订单统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];

        for ($days = $len; $days >= 0; $days--) {
            $l = strtotime("-{$days} days");
            $chart['xAxis']['data'][] = date('m-d', $l);
        }

        $agents = m('agent_vw')->where(We7::uniacid([]))->findAll();
        $index = 0;
        foreach ($agents as $agent) {

            $stats = $agent->get('statsData', []);
            if (isEmptyArray($stats)) {
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

            for ($days = $len; $days >= 0; $days--) {

                $l = strtotime("-{$days} days");
                $y = date('Y', $l);
                $n = date('n', $l);
                $j = date('j', $l);

                $data = $stats['data'][$y]['days'][$n][$j];
                $total = $data['p'] + $data['b'] + $data['f'];
                $chart['series'][$index]['total'] += $total;
                $chart['series'][$index]['data'][] = $total;
            }

            $index++;
        }

        usort(
            $chart['series'],
            function ($a, $b) {
                return $b['total'] - $a['total'];
            }
        );

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
    public static function chartDataOfAccounts($len = 7, $max = 15): array
    {
        $chart = [
            'title' => ['text' => "公众号最近{$len}日订单统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];

        for ($days = $len; $days >= 0; $days--) {
            $l = strtotime("-{$days} days");
            $chart['xAxis']['data'][] = date('m-d', $l);
        }

        $accounts = Account::query(['state' => 1])->findAll();

        $index = 0;
        foreach ($accounts as $acc) {

            $stats = $acc->get('statsData');
            if (isEmptyArray($stats)) {
                continue;
            }

            $chart['series'][$index] = [
                'type' => 'line',
                'smooth' => true,
                'stack' => '总量',
                'areaStyle' => ['normal' => []],
                'color' => Util::randColor(),
                'name' => $acc->getTitle(),
                'data' => [],
            ];

            for ($days = $len; $days >= 0; $days--) {

                $l = strtotime("-{$days} days");
                $y = date('Y', $l);
                $n = date('n', $l);
                $j = date('j', $l);

                $data = $stats['data'][$y]['days'][$n][$j];
                $total = $data['p'] + $data['b'] + $data['f'];
                $chart['series'][$index]['total'] += $total;
                $chart['series'][$index]['data'][] = $total;
            }

            $index++;
        }

        usort(
            $chart['series'],
            function ($a, $b) {
                return $b['total'] - $a['total'];
            }
        );

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
    public static function chartDataOfDevices($len = 7, $max = 15): array
    {
        $chart = [
            'title' => ['text' => "设备最近{$len}日订单统计"],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['height' => '50%', 'left' => 60, 'right' => 30],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'axisLabel' => ['formatter' => '{value}'], 'minInterval' => 1],
            'legend' => ['data' => [], 'bottom' => 0, 'top' => 300],
            'series' => [],
        ];

        for ($days = $len; $days >= 0; $days--) {
            $l = strtotime("-{$days} days");
            $chart['xAxis']['data'][] = date('m-d', $l);
        }

        $devices = m('device_view')
        ->where(We7::uniacid(['m_total >' => 0]))
        ->orderBy('d_total DESC,m_total DESC')
        ->limit($max)
        ->findAll();

        $index = 0;

        /** @var deviceModelObj $device */
        foreach ($devices as $device) {

            $stats = $device->get('statsData');
            if (isEmptyArray($stats)) {
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

            for ($days = $len; $days >= 0; $days--) {

                $l = strtotime("-{$days} days");
                $y = date('Y', $l);
                $n = date('n', $l);
                $j = date('j', $l);

                $data = $stats['data'][$y]['days'][$n][$j];
                $total = $data['p'] + $data['b'] + $data['f'];
                $chart['series'][$index]['total'] += $total;
                $chart['series'][$index]['data'][] = $total;
            }

            $index++;
        }

        // usort($chart['series'], function ($a, $b) {
        //     return $b['total'] - $a['total'];
        // });

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
        $entry = app();
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

        $stats = $entry->get('statsData', []);
        if ($stats) {

            $y = date('Y'); //年
            $n = date('n'); //月
            //$z = date('z'); //一年中的第几天
            $j = date('j'); //月份中的第几天

            $data['all']['n'] = $stats['total']['p'] + $stats['total']['b'] + $stats['total']['f'];
            $today_total = $stats['data'][$y]['days'][$n][$j];
            $data['today']['n'] = $today_total['p'] + $today_total['b'] + $today_total['f'];

            $month_total = $stats['data'][$y]['total'][$n];
            $data['month']['n'] = $month_total['p'] + $month_total['b'] + $month_total['f'];

            for ($index = 0; $index < 7; $index++) {

                $l = strtotime("-{$index} days");

                $y1 = date('Y', $l);
                $n1 = date('n', $l);
                $j1 = date('j', $l);

                $total = $stats['data'][$y1]['days'][$n1][$j1];
                $data['last7days']['n'] += ($total['p'] + $total['b'] + $total['f']);
                if ($index == 1) {
                    $data['yesterday']['n'] = ($total['p'] + $total['b'] + $total['f']);
                }
            }

            $l = strtotime('-1 month');
            $y2 = date('Y', $l);
            $n2 = date('n', $l);

            $total = $stats['data'][$y2]['total'][$n2];
            $data['lastmonth']['n'] = $total['p'] + $total['b'] + $total['f'];
        }

        $query = User::query();
        $data['all']['f'] = $query->count();

        $today = strtotime('today');
        $data['today']['f'] = $query->resetAll()->where(['createtime >=' => strtotime('today')])->count();

        $yesterday = strtotime('yesterday');
        $data['yesterday']['f'] = $query->resetAll()->where(['createtime >=' => $yesterday, 'createtime <' => $today])->count();

        $last7days = strtotime(date('Y-m-d 00:00:00', strtotime('-7 days')));
        $data['last7days']['f'] = $query->resetAll()->where(['createtime >=' => $last7days])->count();

        $month = strtotime(date('Y-m-01 00:00:00'));
        $data['month']['f'] = $query->resetAll()->where(['createtime >=' => $month])->count();

        $lastmonth = strtotime(date('Y-m-01 00:00:00', strtotime('-1 month')));
        $data['lastmonth']['f'] = $query->resetAll()->where(['createtime >=' => $lastmonth, 'createtime <' => $month])->count();

        $total = [
            'device' => Device::query()->count(),
            'agent' => Agent::query()->count(),
            'advs' => Account::query(['state' => 1])->count() + Advertising::query(
                    ['state <>' => Advertising::DELETED]
                )->count(),
            'user' => User::query()->count(),
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
            $way = $order->getPrice() > 0 ? 'p' : ($order->getBalance() > 0 ? 'b' : 'f');
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
            unset($stats['data'][$y]['total'][$n]['b']);
            unset($stats['data'][$y]['total'][$n]['f']);

            $p = 0;
            $b = 0;
            $f = 0;

            foreach ((array)$stats['data'][$y]['days'][$n] as $key => $val) {
                $p += intval($val['p']);
                $b += intval($val['b']);
                $f += intval($val['f']);
            }

            $stats['data'][$y]['total'][$n]['p'] = $p;
            $stats['data'][$y]['total'][$n]['b'] = $b;
            $stats['data'][$y]['total'][$n]['f'] = $f;

            unset($stats['data'][$y]['total']['p']);
            unset($stats['data'][$y]['total']['b']);
            unset($stats['data'][$y]['total']['f']);

            $p = 0;
            $b = 0;
            $f = 0;

            foreach ((array)$stats['data'][$y]['total'] as $key => $val) {
                $p += intval($val['p']);
                $b += intval($val['b']);
                $f += intval($val['f']);
            }

            $stats['data'][$y]['total']['p'] = $p;
            $stats['data'][$y]['total']['b'] = $b;
            $stats['data'][$y]['total']['f'] = $f;

            unset($stats['total']);

            $p = 0;
            $b = 0;
            $f = 0;

            foreach ((array)$stats['data'] as $key => $val) {
                $p += intval($val['total']['p']);
                $b += intval($val['total']['b']);
                $f += intval($val['total']['f']);
            }

            $stats['total'] = [
                'p' => $p,
                'b' => $b,
                'f' => $f,
            ];

            return $obj->set('statsData', $stats);

        } catch (Exception $e) {
            Util::logToFile('stats', [
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
     * @param ISettings $obj
     * @param null $day
     * @return array
     */
    public static function daysOfMonth(ISettings $obj, $day = null): array
    {
        if (empty($day)) {
            $day = time();
        } elseif (is_string($day)) {
            $day = strtotime($day);
        }

        $y = date('Y', $day); //年
        $n = date('n', $day); //月

        $result = [];
        $stats = $obj->get('statsData', []);

        if ($stats) {
            $data = $stats['data'][$y]['days'][$n];
            if ($data) {
                foreach ($data as $index => $entry) {
                    $time = strtotime("{$y}-{$n}-{$index}");
                    $result[date('m-d', $time)] = [
                        'free' => intval($entry['f']),
                        'balance' => intval($entry['b']),
                        'fee' => intval($entry['p']),
                        '_day' => $index,
                    ];
                }
            }
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
     * @param ISettings $obj
     * @param null $day
     * @return array
     */
    public static function hoursOfDay(ISettings $obj, $day = null): array
    {
        if (empty($day)) {
            $day = time();
        } elseif (is_string($day)) {
            $day = strtotime($day);
        }

        $y = date('Y', $day); //年
        //$n = date('n', $day); //月
        $z = date('z', $day); //一年中的第几天
        //$j = date('j', $day); //月份中的第几天

        $result = [];
        $stats = $obj->get('statsData', []);

        if ($stats) {
            $data = $stats['data'][$y]['hours'][$z];
            if ($data) {
                foreach ($data as $index => $entry) {
                    $result["{$index}"] = [
                        'free' => intval($entry['f'] + $entry['b']),
                        'balance' => intval($entry['b']),
                        'fee' => intval($entry['p']),
                    ];
                }
            }

        }

        return $result;
    }
}
