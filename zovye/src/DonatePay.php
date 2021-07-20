<?php
namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

class DonatePay
{
    public static function createPaylog(deviceModelObj $device, userModelObj $user, array $goods, int $num = 1)
    {
        $last = $user->get('donate', []);
        if ($last && $last['order_no']) {
            if (!Order::exists($last['order_no'])) {
                $paylog = Pay::getPayLog($last['order_no']);
                if ($paylog && $paylog->getDeviceId() == $device->getId()) {
                    return $last['order_no'];
                }
            }
        }

        $discount = User::getUserDiscount($user, $goods, $num);
        $price = $goods['price'] * $num - $discount;
        if ($price < 1) {
            return err('支付金额不能为零！');
        }

        list($order_no, $paylog) = Pay::prepareDataWithPay('donate', $device, $user, $goods, [
            'level' => LOG_GOODS_PAY,
            'total' => $num,
            'price' => $price,
            'discount' => $discount,
        ]);

        if (is_error($order_no)) {
            return $order_no;
        }

        $user->set('donate', [
            'order_no' => $order_no,
            'paylog' => $paylog->getId(),
        ]);

        return $order_no;
    }
}