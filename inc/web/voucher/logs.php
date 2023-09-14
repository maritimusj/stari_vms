<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\GoodsVoucher;

defined('IN_IA') or exit('Access Denied');

$params = [];

$voucher_id = Request::int('voucherId');
if ($voucher_id > 0) {
    $params['voucherId'] = $voucher_id;
}

$type = Request::str('type');
if ($type) {
    $params['type'] = $type;
}

$params['page'] = max(1, Request::int('page'));
$params['pagesize'] = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

$res = GoodsVoucher::logList($params);
if (is_error($res)) {
    JSON::fail($res);
}

$pager = We7::pagination($res['total'], $res['page'], $res['pagesize']);

JSON::success([
    'type' => $type,
    'pager' => $pager,
    'logs' => $res['logs'],
]);