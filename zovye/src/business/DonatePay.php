<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\business;

use zovye\domain\Order;
use zovye\domain\User;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Pay;
use function zovye\err;
use function zovye\is_error;

class DonatePay
{
    public static function createPayLog(deviceModelObj $device, userModelObj $user, array $goods, int $num = 1)
    {
        $last = $user->get('donate', []);
        if ($last && $last['order_no']) {
            if (!Order::exists($last['order_no'])) {
                $pay_log = Pay::getPayLog($last['order_no']);
                if ($pay_log && $pay_log->getDeviceId() == $device->getId()) {
                    return $last['order_no'];
                }
            }
        }

        $discount = User::getUserDiscount($user, $goods, $num);
        $price = $goods['price'] * $num - $discount;
        if ($price < 1) {
            return err('支付金额不能为零！');
        }

        list($order_no, $pay_log) = Pay::prepareDataWithPay('donate', $device, $user, $goods, [
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
            'pay_log' => $pay_log->getId(),
        ]);

        return $order_no;
    }
}