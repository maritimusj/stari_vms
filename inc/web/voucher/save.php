<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use Exception;
use zovye\domain\Goods;
use zovye\domain\GoodsVoucher;
use zovye\util\DBUtil;

$res = DBUtil::transactionDo(function () {
    $goods_id = Request::int('goodsId');
    $goods = Goods::get($goods_id);
    if (empty($goods)) {
        return err('要绑定的商品不存在！');
    }

    $ids = Request::is_array('goods') ? Request::array('goods') : [];
    $ids = array_map(function ($id) {
        return intval($id);
    }, $ids);

    $ids = array_filter(array_values($ids), function ($id) {
        return $id != -1;
    });


    $begin = 0;
    $end = 0;

    if (Request::bool('validate')) {
        try {
            $begin = (new DateTime(Request::str('begin')))->getTimestamp();
        } catch (Exception $e) {
        }
        try {
            $end = (new DateTime(Request::str('end')))->getTimestamp();
        } catch (Exception $e) {
        }
    }

    $total = Request::int('total');
    $original_limit_goods_dds = [];

    if (Request::int('id') > 0) {
        $id = Request::int('id');
        $voucher = GoodsVoucher::get($id);
        if (empty($voucher)) {
            return err('找不到指定的提货码！');
        }

        $original_limit_goods_dds = (array)$voucher->getExtraData('limitGoods', []);

        $voucher->setGoodsId($goods_id);
        $voucher->setTotal($total);
        $voucher->setBegin($begin);
        $voucher->setEnd($end);
        $voucher->setExtraData('limitGoods', array_values($ids));

        if (!$voucher->save()) {
            return err('保存失败！');
        }
    } else {
        $voucher = GoodsVoucher::create(null, $goods, $total, $begin, $end, $ids);
        if (empty($voucher)) {
            return err('创建失败！');
        }
    }

    $voucher_id = intval($voucher->getId());

    //在赠送提货券的商品上做记录
    $v = array_diff($original_limit_goods_dds, $ids);
    foreach ($v as $id) {
        $goods = Goods::get($id);
        if ($goods) {
            $x = (array)$goods->getExtraData('vouchers', []);
            $x = array_filter($x, function ($id) use ($voucher_id) {
                return $id != $voucher_id;
            });
            $goods->setExtraData('vouchers', $x);
            if (!$goods->save()) {
                return err('保存数据失败！');
            }
        }
    }

    foreach ($ids as $id) {
        $goods = Goods::get($id);
        if ($goods) {
            $v = (array)$goods->getExtraData('vouchers', []);
            $v[] = $voucher_id;
            $goods->setExtraData('vouchers', array_unique($v));
            if (!$goods->save()) {
                return err('保存数据失败！');
            }
        }
    }

    return true;
});

if (is_error($res)) {
    JSON::fail($res);
}

JSON::success('保存成功 ！');
