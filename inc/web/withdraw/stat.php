<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\model\commission_balanceModelObj;

$query = CommissionBalance::query([
    'src' => CommissionBalance::WITHDRAW,
]);

//统计
$date_limit = Request::array('datelimit');
if ($date_limit['start']) {
    $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'].' 00:00:00');
} else {
    $s_date = new DateTime('first day of this month 00:00:00');
}

if ($date_limit['end']) {
    $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'].' 00:00:00');
    $e_date->modify('next day');
} else {
    $e_date = new DateTime('next day 00:00');
}

$query->where([
    'createtime >=' => $s_date->getTimestamp(),
    'createtime <' => $e_date->getTimestamp(),
]);

$agent_openid = Request::str('agent_openid');
if (!empty($agent_openid)) {
    $query->where(['openid' => $agent_openid]);
}

$data = [];
$total = [
    'unconfirmed' => 0,
    'confirmed' => 0,
    'cancelled' => 0,
    'mchpay' => 0,
];

/** @var commission_balanceModelObj $item */
foreach ($query->findAll() as $item) {
    $state = $item->getExtraData('state');
    if (empty($state)) {
        $state = 'unconfirmed';
    }
    $created_at = date('Y-m-d', $item->getCreatetime());
    if (!isset($data[$created_at])) {
        $data[$created_at]['unconfirmed'] = 0;
        $data[$created_at]['confirmed'] = 0;
        $data[$created_at]['cancelled'] = 0;
        $data[$created_at]['mchpay'] = 0;
    }
    $val = $item->getXVal();
    $data[$created_at][$state] += $val;
    $total[$state] += $val;
}

ksort($data);

$agent_levels = settings('agent.levels');
$commission_enabled = App::isCommissionEnabled();

$tpl_data = [
    'agent_levels' => $agent_levels,
    'commission_enabled' => $commission_enabled,
];

$tpl_data['mch_pay_enabled'] = !empty(settings('pay.wx.pem'));
$tpl_data['s_date'] = $s_date->format('Y-m-d');
$e_date->modify('-1 day');
$tpl_data['e_date'] = $e_date->format('Y-m-d');
$tpl_data['open_id'] = $agent_openid;
$tpl_data['data'] = $data;
$tpl_data['total'] = $total;

app()->showTemplate('web/withdraw/stat', $tpl_data);