<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\GoodsVoucher;
use zovye\model\goods_voucherModelObj;

$query = GoodsVoucher::query();

$keywords = Request::trim('keywords');
if ($keywords) {
    $query->where(['goods_name LIKE' => "%$keywords%"]);
}

$page = max(1, Request::int('page'));
$page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

$total = $query->count();
if (ceil($total / $page_size) < $page) {
    $page = 1;
}

$tpl_data['pager'] = We7::pagination($total, $page, $page_size);

$tpl_data['agent_levels'] = settings('agent.levels');

$vouchers = [];

/** @var goods_voucherModelObj $entry */
foreach ($query->page($page, $page_size)->orderBy('id DESC')->findAll() as $entry) {
    $vouchers[] = GoodsVoucher::format($entry);
}

$tpl_data['vouchers'] = $vouchers;

JSON::success($tpl_data);