<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$goods = Goods::get(Request::int('id'));
if (empty($goods)) {
    JSON::fail('找不到这个商品！');
}

$s1 = $goods->getS1();

switch(Request::trim('w')) {
    case 'free': $s1 = Goods::setFreeBitMask($s1, !$goods->allowFree());break;
    case 'pay': $s1 = Goods::setPayBitMask($s1, !$goods->allowPay());break;
    case 'balance': $s1 = Goods::setBalanceBitMask($s1, !$goods->allowBalance());break;
    case 'delivery': $s1 = Goods::setDeliveryBitMask($s1, !$goods->allowDelivery());break;
}

$goods->setS1($s1);

if ($goods->save()) {
    JSON::success('操作成功！');
}

JSON::fail('操作失败！');