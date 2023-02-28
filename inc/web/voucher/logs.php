<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$params = [];

$voucher_id = request::int('voucherId');
if ($voucher_id > 0) {
    $params['voucherId'] = $voucher_id;
}

$type = request::str('type');
if ($type) {
    $params['type'] = $type;
}

$params['page'] = max(1, request::int('page'));
$params['pagesize'] = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

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