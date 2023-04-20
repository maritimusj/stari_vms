<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\model\gsp_userModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;
use zovye\model\agentModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;
use zovye\model\accountModelObj;
use zovye\model\balanceModelObj;

class CommissionEventHandler
{
    /**
     * 事件：device.orderCreated
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param accountModelObj|null $account
     * @param balanceModelObj|null $balance
     * @return bool
     * @throws Exception
     */
    public static function onDeviceOrderCreated(
        deviceModelObj $device,
        orderModelObj $order,
        accountModelObj $account = null,
        balanceModelObj $balance = null
    ): bool {
        if (!App::isCommissionEnabled()) {
            return true;
        }

        if ($order->isChargingOrder()) {
            return self::charging($device, $order);
        }

        if (App::isZeroBonusEnabled() && $order->isZeroBonus()) {
            return true;
        }

        if ($account) {
            return self::free($device, $order, $account);
        }

        if ($balance) {
            return self::balance($device, $order);
        }

        //小程序激励广告出货
        if ($order->getExtraData('reward')) {
            return self::reward($device, $order);
        }

        if ($order->getPrice() > 0) {
            return self::pay($device, $order);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    protected static function charging(deviceModelObj $device, orderModelObj $order): bool
    {
        $agent = $device->getAgent();
        if (empty($agent)) {
            return true;
        }

        if (!$agent->isCommissionEnabled()) {
            return true;
        }

        $order->setExtraData('commission.local.total', $order->getCommissionPrice());

        //对服务费进行佣金分配
        $sf = $order->getChargingSF();

        if ($sf > 0) {
            $sf = self::processProfit($device, $order, $agent, $sf, $sf, CommissionBalance::CHARGING_SERVICE_FEE);
        }

        $balance = $agent->getCommissionBalance();

        if ($sf > 0) {
            $r1 = $balance->change($sf, CommissionBalance::CHARGING_SERVICE_FEE, ['orderid' => $order->getId()]);
            if ($r1 && $r1->update([], true)) {
                //记录佣金
                $order->setExtraData('commission.agent', [
                    'id' => $r1->getId(),
                    'xval' => $r1->getXVal(),
                    'openid' => $agent->getOpenid(),
                    'name' => $agent->getName(),
                ]);
            } else {
                throw new Exception('创建代理佣金数据失败！', State::ERROR);
            }
        }

        //电费直接分给代理商
        $ef = $order->getChargingEF();
        if ($ef < 1) {
            return true;
        }

        $r2 = $balance->change($ef, CommissionBalance::CHARGING_ELECTRIC_FEE, ['orderid' => $order->getId()]);
        if ($r2 && $r2->update([], true)) {
            //记录佣金
            $order->setExtraData('commission.agent', [
                'id' => $r2->getId(),
                'xval' => $r2->getXVal() + (isset($r1) ? $r1->getXVal() : 0),
                'openid' => $agent->getOpenid(),
                'name' => $agent->getName(),
            ]);
        } else {
            throw new Exception('创建代理佣金数据失败！', State::ERROR);
        }

        return true;
    }

    /**
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @return bool
     * @throws Exception
     */
    protected static function reward(deviceModelObj $device, orderModelObj $order): bool
    {
        $val = intval(Config::app('wxapp.advs.reward.freeCommission', 0));

        $commission_total = $val * $order->getNum();

        if ($commission_total < 1) {
            return true;
        }

        return self::processCommissions($device, $order, $commission_total, $commission_total);
    }

    /**
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @return bool
     * @throws Exception
     */
    protected static function balance(deviceModelObj $device, orderModelObj $order): bool
    {
        $val = intval(Config::balance('order.commission.val', 0));

        $commission_total = $val * $order->getNum();

        if ($commission_total < 1) {
            return true;
        }

        return self::processCommissions($device, $order, $commission_total, $commission_total);
    }

    /**
     * 免费订单分佣
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param accountModelObj|null $account
     * @return bool
     * @throws Exception
     */
    protected static function free(deviceModelObj $device, orderModelObj $order, accountModelObj $account): bool
    {
        if (settings('agent.yzshop.goods_limits.enabled') && YZShop::isInstalled()) {
            $agent = $device->getAgent();
            if ($agent) {
                $stats = Stats::total($agent);
                if ($stats && $stats['total'] >= YZShop::getRestrictGoodsTotal($agent)) {
                    return true;
                }
            }
        }

        $commission_total = $account->getCommissionPrice();

        if ($commission_total < 1) {
            return true;
        }

        return self::processCommissions($device, $order, $commission_total, $commission_total);
    }

    /**
     * 处理支付订单的佣金分配
     * 第1步，扣除平台费用
     * 第2步，计算商品利润（减去成本价）
     * 第3步，对利润进行佣金分配
     *       1，处理佣金分享用户
     *       2，处理运营人员佣金
     * 第4步，成本及剩余利润分配给代理商
     *
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @return bool
     * @throws Exception
     */
    protected static function pay(deviceModelObj $device, orderModelObj $order): bool
    {
        $agent = $device->getAgent();
        if (empty($agent)) {
            return true;
        }

        if (!$agent->isCommissionEnabled()) {
            return true;
        }

        $remaining_total = $order->getCommissionPrice();
        if ($remaining_total < 1) {
            return true;
        }

        //第1步，扣除平台费用
        $remaining_total = self::ProcessFee($remaining_total, $remaining_total, $agent, $order);
        if ($remaining_total < 1) {
            return true;
        }

        $order->setExtraData('commission.local.total', $remaining_total);

        //第2步，计算商品利润（减去成本价）
        $goods = $order->getGoods();

        $cost_price = empty($goods) ? 0 : $goods->getCostPrice() * $order->getNum();

        $remaining_total -= $cost_price;

        //第3步，对利润进行佣金分配
        if ($remaining_total > 0) {
            $remaining_total = self::processProfit($device, $order, $agent, $remaining_total, $remaining_total);
        }

        //第4步，成本及剩余利润分配给代理商, cw 设置为成本是否作为佣金分配给设备代理商
        if ($goods && empty($goods->getExtraData('cw', 0))) {
            //成本参与分佣
            $remaining_total += $cost_price;
        }

        if ($remaining_total < 1) {
            return true;
        }

        //最后，剩余金额直接分给代理商
        $balance = $agent->getCommissionBalance();

        $r = $balance->change($remaining_total, CommissionBalance::ORDER_WX_PAY, ['orderid' => $order->getId()]);
        if ($r && $r->update([], true)) {
            //记录佣金
            $order->setExtraData('commission.agent', [
                'id' => $r->getId(),
                'xval' => $r->getXVal(),
                'openid' => $agent->getOpenid(),
                'name' => $agent->getName(),
            ]);
        } else {
            throw new Exception('创建代理佣金数据失败！', State::ERROR);
        }

        return true;
    }

    /**
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param int $commission_total
     * @param int $remaining_total
     * @return bool
     * @throws Exception
     */
    protected static function processCommissions(
        deviceModelObj $device,
        orderModelObj $order,
        int $commission_total,
        int $remaining_total
    ): bool {

        $agent = $device->getAgent();
        if (empty($agent)) {
            return true;
        }

        if (!$agent->isCommissionEnabled()) {
            return true;
        }

        $order->setExtraData('commission.local.total', $remaining_total);

        //可分佣的金额
        $remaining_total = self::processGSP($commission_total, $remaining_total, $agent, $order);
        if ($remaining_total < 1) {
            return true;
        }

        $remaining_total = self::processKeeperCommissions($commission_total, $remaining_total, $device, $order);
        if ($remaining_total < 1) {
            return true;
        }

        //还有剩余则为代理佣金
        $src = $order->getBalance() > 0 ? CommissionBalance::ORDER_BALANCE : CommissionBalance::ORDER_FREE;
        $r = $agent->commission_change($remaining_total, $src, ['orderid' => $order->getId()]);
        if ($r && $r->update([], true)) {
            //记录代理商所得佣金
            $order->setExtraData(
                'commission.agent',
                [
                    'id' => $r->getId(),
                    'xval' => $r->getXVal(),
                    'openid' => $agent->getOpenid(),
                    'name' => $agent->getName(),
                ]
            );
        } else {
            throw new Exception('创建代理佣金数据失败！', State::FAIL);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    protected static function processPromoterCommissions(
        int $commission_total,
        int $remaining_total,
        keeperModelObj $keeper,
        orderModelObj $order,
        int $src = CommissionBalance::GSP
    ): array {
        $log = [];

        $config = $keeper->settings('promoter.commission', []);
        if (!isEmptyArray($config)) {

            $user = $order->getUser();
            if (empty($user)) {
                return [$remaining_total, []];
            }

            $query = Principal::promoter(['superior_id' => $keeper->getId(), 'user_id' => $user->getId()]);

            /** @var userModelObj $promoter */
            $promoter = $query->findOne();

            if (empty($promoter) || $promoter->isBanned()) {
                return [$remaining_total, []];
            }

            if ($config['percent']) {
                $val = intval(round($commission_total * intval($config['percent']) / 10000));
            } elseif ($config['fixed']) {
                $val = intval($config['fixed'] * $order->getNum());
            } else {
                $val = 0;
            }

            if ($val > $remaining_total) {
                $val = $remaining_total;
            }

            if ($val > 0) {
                $r = $promoter->commission_change($val, $src, ['orderid' => $order->getId()]);
                if ($r && $r->update([], true)) {
                    //记录佣金
                    $log[] = [
                        'id' => $r->getId(),
                        'xval' => $r->getXVal(),
                        'openid' => $promoter->getOpenid(),
                        'name' => $promoter->getName(),
                        'mobile' => $promoter->getMobile(),
                        'promoter' => true,
                    ];
                    $remaining_total -= $val;
                } else {
                    throw new Exception('创建推广员佣金失败！', State::ERROR);
                }
            }
        }

        return [$remaining_total, $log];
    }

    /**
     * 处理运营人员佣金
     * @param int $commission_total
     * @param int $remaining_total
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param int $src
     * @return int
     * @throws Exception
     */
    protected static function processKeeperCommissions(
        int $commission_total,
        int $remaining_total,
        deviceModelObj $device,
        orderModelObj $order,
        int $src = CommissionBalance::GSP
    ): int {
        $keepers = $device->getKeepers();

        $logs = [];

        foreach ($keepers as $keeper) {
            $user = $keeper->getUser();
            if (empty($user)) {
                Log::error('keeper', [
                    'err' => '营运人员对应的用户不存在，忽略佣金分配！',
                    'order' => $order->profile(),
                    'keeper' => [
                        'name' => $keeper->getName(),
                        'mobile' => $keeper->getMobile(),
                    ],
                ]);
                continue;
            }

            //处理推广员佣金
            if (App::isPromoterEnabled()) {
                list($remaining_total, $promoter_logs) = self::processPromoterCommissions(
                    $commission_total,
                    $remaining_total,
                    $keeper,
                    $order,
                    $src
                );

                if ($promoter_logs) {
                    $logs = array_merge($logs, $promoter_logs);
                }

                if ($remaining_total < 1) {
                    break;
                }
            }

            //开始处理运营人员佣金
            list($v, $way, $is_percent) = $keeper->getCommissionValue($device);
            if ($way != Keeper::COMMISSION_ORDER) {
                continue;
            }

            if ($is_percent) {
                $val = intval(round($commission_total * intval($v) / 100));
            } else {
                $val = intval($v * $order->getNum());
            }

            if ($val > $remaining_total) {
                $val = $remaining_total;
            }

            if ($val > 0) {
                $r = $user->commission_change($val, $src, ['orderid' => $order->getId()]);
                if ($r && $r->update([], true)) {
                    //记录佣金
                    $logs[] = [
                        'id' => $r->getId(),
                        'xval' => $r->getXVal(),
                        'openid' => $user->getOpenid(),
                        'name' => $keeper->getName(),
                        'mobile' => $keeper->getMobile(),
                    ];
                    $remaining_total -= $val;
                    if ($remaining_total < 1) {
                        break;
                    }
                } else {
                    throw new Exception('创建运营人员佣金失败！', State::ERROR);
                }
            }
        }

        //保存分佣记录
        $order->setExtraData('commission.keepers', $logs);

        return $remaining_total;
    }

    /**
     * 处理佣金分享用户佣金
     * @param int $commission_total
     * @param int $remaining_total
     * @param agentModelObj $agent
     * @param orderModelObj $order
     * @param int $src
     * @return int
     * @throws Exception
     */
    protected static function processGSP(
        int $commission_total,
        int $remaining_total,
        agentModelObj $agent,
        orderModelObj $order,
        int $src = CommissionBalance::GSP
    ): int {
        $logs = [];

        $createCommissionFN = function ($user, $val) use (
            &$remaining_total,
            $order,
            $src,
            &$logs
        ) {
            if ($val > $remaining_total) {
                $val = $remaining_total;
            }

            if ($val > 0) {
                $gsp_r = $user->commission_change($val, $src, ['orderid' => $order->getId()]);
                if ($gsp_r && $gsp_r->update([], true)) {
                    $logs[] = [
                        'id' => $gsp_r->getId(),
                        'xval' => $gsp_r->getXVal(),
                        'openid' => $user->getOpenid(),
                        'name' => $user->getName(),
                    ];

                    $remaining_total -= $val;
                    if ($remaining_total < 1) {
                        return false;
                    }
                } else {
                    throw new Exception('创建佣金分享失败！', State::ERROR);
                }
            }

            return true;
        };

        if ($agent->getGSPMode() == GSP::REL || $agent->getGSPMode() == GSP::FREE) {
            //获取佣金分享用户列表
            $gsp_users = $agent->getGspUsers();
            foreach ($gsp_users as $entry) {
                //收费订单
                if ($order->getPrice() > 0 || ($order->getBalance() > 0 && Balance::isPayOrder())) {
                    if (!$entry['order']['p']) {
                        continue;
                    }
                }
                //免费订单
                if (($order->getPrice() == 0 && $order->getBalance() == 0) || ($order->getBalance(
                        ) > 0 && Balance::isFreeOrder())) {
                    if (!$entry['order']['f']) {
                        continue;
                    }
                }
                /** @var userModelObj $user */
                $user = $entry['__obj'];
                $percent = $entry['percent'];
                if (empty($user) || $percent <= 0) {
                    continue;
                }
                if ($entry['type'] == 'amount') {
                    $val = intval($percent);
                } else {
                    $val = intval(round($commission_total * $percent / 10000));
                }
                if (!$createCommissionFN($user, $val)) {
                    //佣金为零，退出循环
                    break;
                }
            }
        } elseif ($agent->getGSPMode() == GSP::MIXED) {
            $query = GSP::from($agent);

            /** @var gsp_userModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $user = GSP::getUser($agent, $entry);
                if (empty($user)) {
                    continue;
                }
                //支付订单
                if ($order->getPrice() > 0 && !$entry->isPayOrderIncluded()) {
                    continue;
                }
                //免费订单
                if ($order->getPrice() == 0 && $order->getBalance() == 0 && !$entry->isFreeOrderIncluded()) {
                    continue;
                }
                //积分订单
                if ($order->getBalance() > 0) {
                    if (Balance::isFreeOrder() && !$entry->isFreeOrderIncluded()) {
                        continue;
                    }
                    if (Balance::isPayOrder() && !$entry->isPayOrderIncluded()) {
                        continue;
                    }
                }
                $val = 0;
                if ($entry->isPercent()) {
                    $percent = intval($entry->getVal());
                    if ($percent <= 0) {
                        continue;
                    }
                    $val = intval(round($commission_total * $percent / 10000));
                } elseif ($entry->isAmount()) {
                    $val = intval($entry->getVal());
                }
                if (!$createCommissionFN($user, $val)) {
                    //佣金为零，退出循环
                    break;
                }
            }
        }

        if ($logs) {
            $order->setExtraData('commission.gsp', $logs);
        }

        return $remaining_total;
    }

    /**
     * 对商品利润进行佣金分配
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param agentModelObj $agent
     * @param int $commission_total
     * @param int $remaining_total
     * @param int $src
     * @return int
     * @throws Exception
     */
    protected static function processProfit(
        deviceModelObj $device,
        orderModelObj $order,
        agentModelObj $agent,
        int $commission_total,
        int $remaining_total,
        int $src = CommissionBalance::GSP
    ): int {

        //处理佣金分享用户
        $remaining_total = self::processGSP($commission_total, $remaining_total, $agent, $order, $src);
        if ($remaining_total < 1) {
            return 0;
        }

        //处理运营人员佣金
        return self::processKeeperCommissions($commission_total, $remaining_total, $device, $order, $src);
    }

    /**
     * 处理平台手续费
     * @param int $commission_total
     * @param int $remaining_total
     * @param agentModelObj $agent
     * @param orderModelObj $order
     * @return int
     */
    protected static function ProcessFee(
        int $commission_total,
        int $remaining_total,
        agentModelObj $agent,
        orderModelObj $order
    ): int {
        $agent_data = $agent->getAgentData();

        if ($agent_data && !empty($agent_data['commission'])) {
            $fee = intval($agent_data['commission']['fee']);
            if ($fee > 0) {
                $fee_type = intval($agent_data['commission']['fee_type']);

                if ($fee_type == 0) {
                    $val = $fee * $order->getNum();
                } else {
                    $val = intval(round($commission_total * $fee / 10000));
                }

                if ($val > $remaining_total) {
                    $val = $remaining_total;
                }

                //记录手续费
                $order->setExtraData('pay.fee', $val);
                $remaining_total -= $val;
            }
        }

        return $remaining_total;
    }
}
