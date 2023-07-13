<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$voucher = GoodsVoucher::get($id);
if ($voucher) {
    $res = DBUtil::transactionDo(function () use ($voucher) {
        if ($voucher->destroy()) {
            return true;
        }

        return err('fail');
    });
    if (!is_error($res)) {
        JSON::success('操作成功 ！');
    }
}

JSON::success('操作失败 ！');