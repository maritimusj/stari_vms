<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$voucher = GoodsVoucher::get($id);
if ($voucher) {
    $res = Util::transactionDo(function () use ($voucher) {
        if ($voucher->destroy()) {
            return true;
        }

        return error(State::ERROR, 'fail');
    });
    if (!is_error($res)) {
        JSON::success('操作成功 ！');
    }
}

JSON::success('操作失败 ！');