<?php

namespace zovye;

use DateTime;

//平台 统计
$date_limit = request::array('datelimit');
if ($date_limit['start']) {
    $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'].' 00:00:00');
} else {
    $s_date = new DateTime('00:00:00');
}

if ($date_limit['end']) {
    $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'].' 00:00:00');
    $e_date->modify('next day');
} else {
    $e_date = new DateTime('next day 00:00:00');
}

$condition = [
    'createtime >=' => $s_date->getTimestamp(),
    'createtime <' => $e_date->getTimestamp(),
];

$data = [];
$total = [
    'order_fee' => 0,
    'comm_fee' => 0,
    'total_fee' => 0,
];

//订单分成
$query = Order::query($condition);

/** @var orderModelObj $item */
foreach ($query->findAll() as $item) {
    if ($item->getExtraData('pay') && !$item->getExtraData('refund')) {
        $val = abs($item->getExtraData('pay')['fee']);
        $create_date = date('Y-m-d', $item->getCreatetime());
        if (!isset($data[$create_date])) {
            $data[$create_date]['order_fee'] = 0;
            $data[$create_date]['comm_fee'] = 0;
            $data[$create_date]['total_fee'] = 0;
        }
        $data[$create_date]['order_fee'] += $val;
        $data[$create_date]['total_fee'] += $val;
        $total['order_fee'] += $val;
        $total['total_fee'] += $val;
    }
}

//提现分成
$cond = array_merge($condition, ['src' => CommissionBalance::FEE]);
$commission_query = CommissionBalance::query($cond);

/** @var commission_balanceModelObj $item */
foreach ($commission_query->findAll() as $item) {
    $val = abs($item->getXVal());
    $create_date = date('Y-m-d', $item->getCreatetime());
    if (!isset($data[$create_date])) {
        $data[$create_date]['order_fee'] = 0;
        $data[$create_date]['comm_fee'] = 0;
        $data[$create_date]['total_fee'] = 0;
    }
    $data[$create_date]['comm_fee'] += $val;
    $data[$create_date]['total_fee'] += $val;
    $total['comm_fee'] += $val;
    $total['total_fee'] += $val;
}

krsort($data);

$tpl_data = [];
$tpl_data['data'] = $data;
$tpl_data['total'] = $total;

$tpl_data['s_date'] = $s_date->format('Y-m-d');
$e_date->modify('-1 day');
$tpl_data['e_date'] = $e_date->format('Y-m-d');

app()->showTemplate('web/account/platform_stats', $tpl_data);