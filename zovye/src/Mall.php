<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\balanceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\userModelObj;

class Mall
{
    public static function getGoodsList($params = []): array
    {
        $formatter = function (goodsModelObj $goods) {
            $data = [
                'id' => $goods->getId(),
                'name' => strval($goods->getName()),
                'img' => Util::toMedia($goods->getImg(), true),
                'price' => intval($goods->getPrice()),
                'price_formatted' => '￥' . number_format($goods->getPrice() / 100, 2) . '元',
                'unit_title' => $goods->getUnitTitle(),
                'balance' => $goods->getBalance(),
                'createtime_formatted' => date('Y-m-d H:i:s', $goods->getCreatetime()),
            ];
    
            $cost_price = $goods->getCostPrice();
    
            if (!empty($cost_price)) {
                $data['costPrice'] = $cost_price;
                $data['costPrice_formatted'] = '￥' . number_format($cost_price / 100, 2) . '元';
            }
            $discountPrice = $goods->getExtraData('discountPrice', 0);
            if (!empty($discountPrice)) {
                $data['discountPrice'] = $discountPrice;
                $data['discountPrice_formatted'] = '￥' . number_format($discountPrice / 100, 2) . '元';
            }
            $detailImg = $goods->getDetailImg();
            if ($detailImg) {
                $data['detailImg'] = Util::toMedia($goods->getDetailImg(), true);
            }
            $gallery = $goods->getGallery();
            if ($detailImg && (empty($gallery) || $gallery[0] != $detailImg)) {
                $gallery[] = $detailImg;
            }
    
            if ($gallery) {
                foreach ($gallery as $url) {
                    $data['gallery'][] = Util::toMedia($url, true);
                }
            }
            return $data;
        };
    
        $params = [
            'page' => $params['page'] ?? 1,
            'pagesize' => $params['pagesize'] ?? DEFAULT_PAGE_SIZE,
            Goods::AllowDelivery,
        ];
    
        $result = Goods::getList($params, $formatter);
    
        foreach ($result['list'] as &$goods) {
            $goods['total'] = (int)Delivery::query()->where(['goods_id' => $goods['id']])->sum('num');
        }
    
        return $result;
    }

    public static function createOrder(userModelObj $user, $params = [])
    {
        $goods = Goods::get($$params['goods_id']);
        if (empty($goods)) {
            return err('找不到这个商品！');
        }
    
        if (!$goods->allowDelivery()) {
            return err('无法兑换这个商品！');
        }
    
        $num = $params['num'] ?? 1;
        if ($num < 1) {
            return err('商品数量不能为零！');
        }
    
        $recipient = $user->getRecipientData();
    
        $name = $params['name'] ?? $recipient['name'];
        $phone_num = $params['phoneNum'] ?? $recipient['phoneNum'];
        $address = $params['address'] ?? $recipient['address'];
    
        if (empty($phone_num) || empty($address)) {
            return err('没有收件人的手机号码或地址！');
        }
    
        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }
    
        $balance = $user->getBalance();
    
        $result = Util::transactionDo(function () use ($user, $balance, $goods, $num, $phone_num, $address, $name) {
            $total_balance = $goods->getBalance() * $num;
            if ($total_balance > $balance->total()) {
                return err('您的积分不够！');
            }
    
            $x = $balance->change(-$total_balance, Balance::DELIVERY_ORDER, [
                'goods' => $goods->getId(),
                'num' => $num,
            ]);
            if (empty($x)) {
                return err('积分操作失败！');
            }
    
            $order = Delivery::create([
                'order_no' => Delivery::makeUID($user, time()),
                'user_id' => $user->getId(),
                'goods_id' => $goods->getId(),
                'num' => $num,
                'name' => $name,
                'phone_num' => $phone_num,
                'address' => $address,
                'status' => Delivery::PAYED,
                'extra' => [
                    'goods' => Goods::format($goods),
                    'balance' => [
                        'id' => $x->getId(),
                        'xval' => $x->getXVal(),
                    ],
                ]
            ]);
    
            if (empty($order)) {
                return err('创建订单出错！');
            }
    
            $x->setExtraData('order.id', $order->getId());
            if (!$x->save()) {
                return err('保存数据失败！');
            }
    
            return $x;
        });
    
    
        if (is_error($result)) {
            return $result;
        }
    
        return [
            'total' => $balance->total(),
            'xval' => $result instanceof balanceModelObj ? $result->getXVal() : 0,
        ];
    }
}