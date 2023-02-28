<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$voucher = GoodsVoucher::get($id);
if ($voucher) {
    $data = GoodsVoucher::format($voucher);
    $data['limitGoods'] = array_values((array)$voucher->getExtraData('limitGoods', []));
    if ($voucher->getBegin() > 0) {
        $data['begin'] = date('Y-m-d', $voucher->getBegin());
    }
    if ($voucher->getEnd() > 0) {
        $data['end'] = date('Y-m-d', $voucher->getEnd());
    }

    JSON::success($data);
}

JSON::fail('找不到指定的提货码！');