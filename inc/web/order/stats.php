<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//统计 订单金额
use DateTime;
use zovye\model\orderModelObj;

$agent_openid = Request::str('agent_openid');
$device_id = Request::int('device_id');

$date_limit = Request::array('datelimit');
$start = empty($date_limit['start']) ? new DateTime('00:00:00') : DateTime::createFromFormat(
    'Y-m-d H:i:s',
    $date_limit['start'].' 00:00:00'
);
$end = empty($date_limit['end']) ? new DateTime() : DateTime::createFromFormat(
    'Y-m-d H:i:s',
    $date_limit['end'].' 00:00:00'
);

if (!($start && $end)) {
    Response::itoast('时间不正确！', $this->createWebUrl('order', ['op' => 'stat']), 'error');
}

$tpl_data['s_date'] = $start->format('Y-m-d');
$tpl_data['e_date'] = $end->format('Y-m-d');

$total = [];
$data = [];

$end->modify('next day 00:00:00');

while ($start < $end) {
    $start_ts = $start->getTimestamp();

    $start->modify('+1 day');

    $end_ts = $start->getTimestamp();

    list($t1, $d1) = calc_stats($agent_openid, $device_id, $start_ts, $end_ts);

    foreach ($t1 as $index => $item) {
        $total[$index] += $item;
    }

    foreach ($d1 as $date_str => $item) {
        foreach ($item as $index => $x) {
            $data[$date_str][$index] += $x;
        }
    }
}

$tpl_data['open_id'] = $agent_openid;
$tpl_data['device_id'] = $device_id;
$tpl_data['data'] = array_reverse($data);
$tpl_data['total'] = $total;

app()->showTemplate('web/order/stats', $tpl_data);

function calc_stats($agent_openid, $device_id, $start, $end): array
{
    $condition = [
        'createtime >=' => $start,
        'createtime <' => $end,
    ];

    if (!empty($agent_openid)) {
        $agent = Agent::get($agent_openid, true);
        if ($agent) {
            $condition['agent_id'] = $agent->getId();
        } else {
            $condition['agent_id'] = -1;
        }
    }
    if (!empty($device_id)) {
        $condition['device_id'] = $device_id;
    }

    $data = [];
    $total = [
        'income' => 0,
        'refund' => 0,
        'receipt' => 0,
        'wx_income' => 0,
        'wx_refund' => 0,
        'wx_receipt' => 0,
        'ali_income' => 0,
        'ali_refund' => 0,
        'ali_receipt' => 0,
    ];

    $query = Order::query($condition);

    /** @var orderModelObj $item */
    foreach ($query->findAll() as $item) {

        $amount = $item->getPrice();

        $create_date = date('Y-m-d', $item->getCreatetime());

        if (!isset($data[$create_date])) {
            $data[$create_date]['income'] = 0;
            $data[$create_date]['refund'] = 0;
            $data[$create_date]['receipt'] = 0;
            $data[$create_date]['wx_income'] = 0;
            $data[$create_date]['wx_refund'] = 0;
            $data[$create_date]['wx_receipt'] = 0;
            $data[$create_date]['ali_income'] = 0;
            $data[$create_date]['ali_refund'] = 0;
            $data[$create_date]['ali_receipt'] = 0;
        }

        $is_alipay = User::isAliUser($item->getOpenid());

        $data[$create_date]['income'] += $amount;
        $total['income'] += $amount;
        if ($is_alipay) {
            $data[$create_date]['ali_income'] += $amount;
            $total['ali_income'] += $amount;
        } else {
            $data[$create_date]['wx_income'] += $amount;
            $total['wx_income'] += $amount;
        }

        if ($item->getExtraData('refund')) {
            //如果是退款
            $data[$create_date]['refund'] += $amount;
            $total['refund'] += $amount;
            if ($is_alipay) {
                $data[$create_date]['ali_refund'] += $amount;
                $total['ali_refund'] += $amount;
            } else {
                $data[$create_date]['wx_refund'] += $amount;
                $total['wx_refund'] += $amount;
            }
        } else {
            $data[$create_date]['receipt'] += $amount;
            $total['receipt'] += $amount;
            if ($is_alipay) {
                $data[$create_date]['ali_receipt'] += $amount;
                $total['ali_receipt'] += $amount;
            } else {
                $data[$create_date]['wx_receipt'] += $amount;
                $total['wx_receipt'] += $amount;
            }
        }
    }

    return [$total, $data];
}
