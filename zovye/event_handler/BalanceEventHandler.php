<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\model\userModelObj;
use zovye\model\orderModelObj;
use zovye\model\accountModelObj;

class BalanceEventHandler
{

    /**
     * 事件：device.orderCreated
     * @param userModelObj $user
     * @param orderModelObj $order
     * @param accountModelObj|null $account
     * @throws Exception
     */
    public static function onDeviceOrderCreated(userModelObj $user, orderModelObj $order, accountModelObj $account = null)
    {
        if (is_null($account)) {
            return;
        }

        //是否扣除余额
        $balance_deduct_num = $account->balance();
        $balance_used = App::isUserCenterEnabled() && $balance_deduct_num > 0;

        //是否扣除余额
        if ($balance_used) {
            $user_balance = $user->getBalance();
            if ($user_balance->total() < $balance_deduct_num) {
                throw new Exception('领取失败，余额不足', State::ERROR);
            }

            $r = $user_balance->change(-$balance_deduct_num, Balance::ORDER);
            if ($r) {

                $order->setBalance($balance_deduct_num);
                $order->setOrderId("B{$r->getId()}");

                $r->setMemo("orderid:{$order->getId()}");
                $r->save();

            } else {
                throw new Exception('无法扣除余额，操作失败', State::ERROR);
            }
        }
    }
}
