<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\CommissionBalance;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;

class AgentBonusEventHandler
{
    /**
     * 事件：device.orderCreated
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @return bool
     */
    public static function onDeviceOrderCreated(deviceModelObj $device, orderModelObj $order): bool
    {
        if (!App::isCommissionEnabled()) {
            return true;
        }

        $agent = $device->getAgent();
        if (empty($agent) || !$agent->isCommissionEnabled() || !$agent->settings('agentData.bonus.enabled')) {
            return true;
        }

        //免费订单
        if ($order->isFree() && !$agent->settings('agentData.bonus.order.f')) {
            return true;
        }

        //支付订单
        if ($order->isPay() && !$agent->settings('agentData.bonus.order.p')) {
            return true;
        }

        $agents = ['level0' => $agent];

        $level = 1;
        $superior = $agent->getSuperior();
        while ($superior && $level <= 3) {
            if ($superior->isCommissionEnabled()) {
                $agents['level'.$level] = $superior;
            }
            $superior = $superior->getSuperior();
            $level++;
        }

        $principal = $agent->settings('agentData.bonus.principal', CommissionBalance::PRINCIPAL_ORDER);

        $bonus_log = [];
        foreach ($agents as $level => $user) {
            $amount = $agent->settings("agentData.bonus.$level", 0);
            if ($amount > 0) {
                if ($principal == CommissionBalance::PRINCIPAL_GOODS) {
                    $amount *= $order->getNum();
                }
                $r = $user->commission_change($amount, CommissionBalance::BONUS, ['orderid' => $order->getId()]);
                if ($r && $r->update([], true)) {
                    $bonus_log[] = [
                        'id' => $r->getId(),
                        'xval' => $r->getXVal(),
                        'openid' => $user->getOpenid(),
                        'name' => $user->getName(),
                    ];
                }
            }
        }

        if ($bonus_log) {
            $order->setExtraData('commission.bonus', $bonus_log);
        }

        return true;
    }
}
