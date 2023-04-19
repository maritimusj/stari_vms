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

        $sf = $order->getChargingSF();

        //第3步，对服务费进行佣金分配
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
        $commission = intval(Config::app('wxapp.advs.reward.freeCommission', 0));

        $commission_total = $commission * $order->getNum();

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

        $available_price = $order->getCommissionPrice();
        if ($available_price < 1) {
            return true;
        }

        //第1步，扣除平台费用
        $available_price = self::ProcessFee($available_price, $available_price, $agent, $order);
        if ($available_price < 1) {
            return true;
        }

        $order->setExtraData('commission.local.total', $available_price);

        //第2步，计算商品利润（减去成本价）
        $goods = $order->getGoods();

        $costPrice = empty($goods) ? 0 : $goods->getCostPrice() * $order->getNum();

        $available_price -= $costPrice;

        //第3步，对利润进行佣金分配
        if ($available_price > 0) {
            $available_price = self::processProfit($device, $order, $agent, $available_price, $available_price);
        }

        //第4步，成本及剩余利润分配给代理商
        if ($goods && empty($goods->getExtraData('cw', 0))) {
            //成本参与分佣
            $available_price += $costPrice;
        }

        if ($available_price < 1) {
            return true;
        }

        $balance = $agent->getCommissionBalance();

        $r = $balance->change($available_price, CommissionBalance::ORDER_WX_PAY, ['orderid' => $order->getId()]);
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
     * @param int $available_price
     * @return bool
     * @throws Exception
     */
    protected static function processCommissions(
        deviceModelObj $device,
        orderModelObj $order,
        int $commission_total,
        int $available_price
    ): bool {

        $agent = $device->getAgent();
        if (empty($agent)) {
            return true;
        }

        if (!$agent->isCommissionEnabled()) {
            return true;
        }

        $order->setExtraData('commission.local.total', $available_price);

        //可分佣的金额
        $available_price = self::processGSP($commission_total, $available_price, $agent, $order);
        if ($available_price < 1) {
            return true;
        }

        $available_price = self::processKeeperCommissions($commission_total, $available_price, $device, $order);
        if ($available_price < 1) {
            return true;
        }

        //还有余额则为代理佣金
        $src = $order->getBalance() > 0 ? CommissionBalance::ORDER_BALANCE : CommissionBalance::ORDER_FREE;
        $r = $agent->commission_change($available_price, $src, ['orderid' => $order->getId()]);
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
        int $available_price,
        keeperModelObj $keeper,
        deviceModelObj $device,
        orderModelObj $order,
        int $src = CommissionBalance::GSP
    ): array {
        $log = [];

        $config = $keeper->settings('promoter.commission', []);
        if (!isEmptyArray($config)) {

            $user = $order->getUser();
            if (empty($user)) {
                return [$available_price, []];
            }

            $query = Principal::promoter(['superior_id' => $keeper->getId()]);

            /** @var userModelObj $promoter */
            foreach ($query->findAll() as $promoter) {
                if ($promoter->isBanned() || $promoter->getId() != $user->getId()) {
                    continue;
                }

                if ($config['percent']) {
                    $price = intval(round($commission_total * intval($config['percent']) / 100));
                } elseif ($config['fixed']) {
                    $price = intval($config['fixed'] * $order->getNum());
                } else {
                    $price = 0;
                }

                if ($price > $available_price) {
                    $price = $available_price;
                }

                if ($price > 0) {
                    $r = $promoter->commission_change($price, $src, ['orderid' => $order->getId()]);
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
                        $available_price -= $price;
                        if ($available_price == 0) {
                            break;
                        }
                    } else {
                        throw new Exception('创建推广员佣金失败！', State::ERROR);
                    }
                }
            }
        }

        return [$available_price, $log];
    }

    /**
     * 处理运营人员佣金
     * @param int $commission_total
     * @param int $available_price
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param int $src
     * @return int
     * @throws Exception
     */
    protected static function processKeeperCommissions(
        int $commission_total,
        int $available_price,
        deviceModelObj $device,
        orderModelObj $order,
        int $src = CommissionBalance::GSP
    ): int {
        $keepers = $device->getKeepers();

        $log = [];

        foreach ($keepers as $keeper) {
            $user = $keeper->getUser();
            if (empty($user)) {
                Log::error('keeper', [
                    'err' => '营运人员对应的用户不存在！',
                    'keeper' => [
                        'name' => $keeper->getName(),
                        'mobile' => $keeper->getMobile(),
                    ],
                ]);
                continue;
            }

            //开始处理推广员佣金
            if (App::isPromoterEnabled()) {
                list($promoter_log, $available_price) = self::processPromoterCommissions(
                    $commission_total,
                    $available_price,
                    $keeper,
                    $device,
                    $order,
                    $src
                );
                $log = array_merge($log, $promoter_log);
            }

            //开始处理运营人员佣金
            list($v, $way, $is_percent) = $keeper->getCommissionValue($device);
            if ($way != Keeper::COMMISSION_ORDER) {
                continue;
            }

            if ($is_percent) {
                $price = intval(round($commission_total * intval($v) / 100));
            } else {
                $price = intval($v * $order->getNum());
            }

            if ($price > $available_price) {
                $price = $available_price;
            }

            if ($price > 0) {
                $r = $user->commission_change($price, $src, ['orderid' => $order->getId()]);
                if ($r && $r->update([], true)) {
                    //记录佣金
                    $log[] = [
                        'id' => $r->getId(),
                        'xval' => $r->getXVal(),
                        'openid' => $user->getOpenid(),
                        'name' => $keeper->getName(),
                        'mobile' => $keeper->getMobile(),
                    ];
                    $available_price -= $price;
                    if ($available_price == 0) {
                        break;
                    }
                } else {
                    throw new Exception('创建运营人员佣金失败！', State::ERROR);
                }
            }
        }

        //保存分佣记录
        $order->setExtraData('commission.keepers', $log);

        return $available_price;
    }

    /**
     * 处理佣金分享用户佣金
     * @param int $commission_total
     * @param int $available_price
     * @param agentModelObj $agent
     * @param orderModelObj $order
     * @param int $src
     * @return int
     * @throws Exception
     */
    protected static function processGSP(
        int $commission_total,
        int $available_price,
        agentModelObj $agent,
        orderModelObj $order,
        int $src = CommissionBalance::GSP
    ): int {
        $gsp_log = [];

        $createCommission = function ($price, $user) use (
            $commission_total,
            &$available_price,
            $order,
            $src,
            &$gsp_log
        ) {
            if ($price > $available_price) {
                $price = $available_price;
            }
            if ($price > 0) {
                $gsp_r = $user->commission_change($price, $src, ['orderid' => $order->getId()]);
                if ($gsp_r && $gsp_r->update([], true)) {
                    $gsp_log[] = [
                        'id' => $gsp_r->getId(),
                        'xval' => $gsp_r->getXVal(),
                        'openid' => $user->getOpenid(),
                        'name' => $user->getName(),
                    ];

                    $available_price -= $price;
                    if ($available_price == 0) {
                        return false;
                    }
                } else {
                    throw new Exception('创建佣金分享失败！'.$price, State::ERROR);
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
                    $price = intval($percent);
                } else {
                    $price = intval(round($commission_total * $percent / 10000));
                }
                $more = $createCommission($price, $user);
                if (!$more) {
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
                $price = 0;
                if ($entry->isPercent()) {
                    $percent = intval($entry->getVal());
                    if ($percent <= 0) {
                        continue;
                    }
                    $price = intval(round($commission_total * $percent / 10000));
                } elseif ($entry->isAmount()) {
                    $price = intval($entry->getVal());
                }
                $more = $createCommission($price, $user);
                if (!$more) {
                    //佣金为零，退出循环
                    break;
                }
            }
        }

        if ($gsp_log) {
            $order->setExtraData('commission.gsp', $gsp_log);
        }

        return $available_price;
    }

    /**
     * 对商品利润进行佣金分配
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param agentModelObj $agent
     * @param int $commission_total
     * @param int $available_price
     * @param int $src
     * @return int
     * @throws Exception
     */
    protected static function processProfit(
        deviceModelObj $device,
        orderModelObj $order,
        agentModelObj $agent,
        int $commission_total,
        int $available_price,
        int $src = CommissionBalance::GSP
    ): int {

        //处理佣金分享用户
        $available_price = self::processGSP($commission_total, $available_price, $agent, $order, $src);
        if ($available_price < 1) {
            return 0;
        }

        //处理运营人员佣金
        return self::processKeeperCommissions($commission_total, $available_price, $device, $order, $src);
    }

    /**
     * 处理平台手续费
     * @param int $commission_total
     * @param int $available_price
     * @param agentModelObj $agent
     * @param orderModelObj $order
     * @return int
     */
    protected static function ProcessFee(
        int $commission_total,
        int $available_price,
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

                if ($val > $available_price) {
                    $val = $available_price;
                }

                //记录手续费
                $order->setExtraData('pay.fee', $val);
                $available_price -= $val;
            }
        }

        return $available_price;
    }
}
