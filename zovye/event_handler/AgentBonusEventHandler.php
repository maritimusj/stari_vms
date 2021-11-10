<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

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
        if (empty($agent) ||
            !$agent->settings('agentData.commission.enabled') ||
            !$agent->settings('agentData.bonus.enabled')) {
            return true;
        }

        if (settings('agent.yzshop.goods_limits.enabled') && YZShop::isInstalled()) {
            $stats = Stats::total($agent);
            if ($stats && $stats['total'] >= YZShop::getRestrictGoodsTotal($agent)) {
                return true;
            }
        }

        //免费订单
        if (empty($agent->settings('agentData.bonus.order.f')) &&
            $order->getPrice() == 0 &&
            $order->getBalance() == 0) {
            return true;
        }

        //余额订单
        if (empty($agent->settings('agentData.bonus.order.b')) &&
            $order->getBalance() > 0) {
            return true;
        }

        //支付订单
        if (empty($agent->settings('agentData.bonus.order.p')) &&
            $order->getPrice() > 0) {
            return true;
        }

        $agents = ['level0' => $agent];

        $level = 1;
        $superior = $agent->getSuperior();
        while ($superior && $level <= 3) {
            if ($superior->settings('agentData.commission.enabled')) {
                $agents['level' . $level] = $superior;
            }
            $superior = $superior->getSuperior();
            $level++;
        }

        $bonus_log = [];
        foreach ($agents as $level => $user) {
            $money = intval($agent->settings("agentData.bonus.{$level}"));
            if ($money > 0) {
                $r = $user->commission_change($money, CommissionBalance::BONUS, ['orderid' => $order->getId()]);
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
