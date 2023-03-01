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
    $enabled = $voucher->getEnable();
    $voucher->setEnable(!$enabled);
    $voucher->save();

    JSON::success(['msg' => '操作成功 ！', 'enabled' => $voucher->getEnable()]);
}

JSON::fail('操作失败！');