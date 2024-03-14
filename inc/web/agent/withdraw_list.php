<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Keeper;
use zovye\domain\Withdraw;
use zovye\model\commission_balanceModelObj;

defined('IN_IA') or exit('Access Denied');

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$user = $keeper->getUser();
if (empty($user)) {
    JSON::fail('找不到这个运营人员对应的用户！');
}

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', 10);

$query = Withdraw::query(['openid' => $user->getOpenid()])->where('(updatetime IS NULL OR updatetime=0)');

$total = $query->count();

$total_page = ceil($total / $page_size);
if ($page > $total_page) {
    $page = 1;
}

$tpl_data = [
    'mch_pay_enabled' => !empty(settings('pay.wx.pem')),
];

$apps = [];
if ($total > 0) {
    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    /** @var commission_balanceModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $apps[] = Withdraw::format($entry);
    }
}

$tpl_data['apps'] = $apps;

Response::templateJSON('web/agent/keeper_withdraw',"{$keeper->getName()}的提现申请", $tpl_data);