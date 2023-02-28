<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use DateTime;
use Exception;

$res = Util::transactionDo(function () {
    $goods_id = request::int('goodsId');
    $goods = Goods::get($goods_id);
    if (empty($goods)) {
        return error(State::ERROR, '要绑定的商品不存在！');
    }

    $ids = request::is_array('goods') ? request::array('goods') : [];
    $ids = array_map(function ($id) {
        return intval($id);
    }, $ids);

    $ids = array_filter(array_values($ids), function ($id) {
        return $id != -1;
    });


    $begin = 0;
    $end = 0;

    if (request('validate')) {
        try {
            $begin = (new DateTime(request('begin')))->getTimestamp();
        } catch (Exception $e) {
        }
        try {
            $end = (new DateTime(request('end')))->getTimestamp();
        } catch (Exception $e) {
        }
    }

    $total = request::int('total');
    $original_limit_goods_dds = [];

    if (request('id') > 0) {
        $id = request::int('id');
        $voucher = GoodsVoucher::get($id);
        if (empty($voucher)) {
            return error(State::ERROR, '找不到指定的提货码！');
        }

        $original_limit_goods_dds = (array)$voucher->getExtraData('limitGoods', []);

        $voucher->setGoodsId($goods_id);
        $voucher->setTotal($total);
        $voucher->setBegin($begin);
        $voucher->setEnd($end);
        $voucher->setExtraData('limitGoods', array_values($ids));

        if (!$voucher->save()) {
            return error(State::ERROR, '保存失败！');
        }
    } else {
        $voucher = GoodsVoucher::create(null, $goods, $total, $begin, $end, $ids);
        if (empty($voucher)) {
            return error(State::ERROR, '创建失败！');
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
                return error(State::ERROR, '保存数据失败！');
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
                return error(State::ERROR, '保存数据失败！');
            }
        }
    }

    return true;
});

if (is_error($res)) {
    JSON::fail($res);
}

JSON::success('保存成功 ！');
