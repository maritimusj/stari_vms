<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\event;

use Exception;
use zovye\App;
use zovye\model\userModelObj;
use zovye\State;
use function zovye\settings;

class We7CreditEventHandler
{
    /**
     * 事件：device.orderCreated
     * @param userModelObj $user
     * @throws Exception
     */
    public static function onDeviceOrderCreated(userModelObj $user)
    {
        if (App::isWe7CreditEnabled()) {

            //使用微擎积分
            $credit_require = settings('we7credit.require', 0);
            $credit = $user->getWe7credit();

            if ($credit->total() < $credit_require) {
                throw new Exception('领取失败，会员积分不足', State::ERROR);
            }

            $credit_val = settings('we7credit.val', 0);
            if ($credit_val && !$credit->change($credit_val)) {
                throw new Exception('无法操作会员积分，操作失败', State::ERROR);
            }
        }
    }
}
