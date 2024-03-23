<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\User;
use zovye\domain\Withdraw;
use zovye\model\commission_balanceModelObj;
use zovye\util\Util;

$agent_levels = settings('agent.levels');
$commission_enabled = App::isCommissionEnabled();

$tpl_data = [
    'agent_levels' => $agent_levels,
    'commission_enabled' => $commission_enabled,
    'search_url' => Util::url('withdraw'),
];

$tpl_data['mch_pay_enabled'] = !empty(settings('pay.wx.pem'));

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = Withdraw::query();

if (Request::has('user')) {
    $user_x = User::get(Request::int('user'));
    if ($user_x) {
        $tpl_data['user'] = $user_x->profile();
        $query->where(['openid' => $user_x->getOpenid()]);
    }
}

$total = $query->count();

$total_page = ceil($total / $page_size);
if ($page > $total_page) {
    $page = 1;
}

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

Response::showTemplate('web/withdraw/default', $tpl_data);