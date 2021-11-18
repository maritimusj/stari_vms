<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\model\gsp_userModelObj;
use zovye\model\userModelObj;
use zovye\model\agentModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;
use zovye\model\accountModelObj;

class CommissionEventHandler
{
    /**
     * 事件：device.orderCreated
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param orderModelObj $order
     * @param accountModelObj|null $account
     * @return bool
     * @throws Exception
     */
    public static function onDeviceOrderCreated(deviceModelObj $device, userModelObj $user, orderModelObj $order, accountModelObj $account = null): bool
    {
        if ($account) {
            return self::free($user, $device, $order, $account);
        }

        //如果订单支持不是系统支付商户号，则不参与分佣
        // if (settings('lcsw.enabled')) {
        //     $merchant_no = $order->getExtraData('payResult.raw.merchant_no');
        //     if ($merchant_no && $merchant_no != settings('lcsw.merchant_no')) {
        //         return true;
        //     }
        // }

        if ($order->getPrice() > 0) {
            return self::pay($device, $order);
        }

        return true;
    }


    /**
     * 免费订单分佣
     * @param userModelObj $user
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @param accountModelObj|null $account
     * @return bool
     * @throws Exception
     */
    protected static function free(userModelObj $user, deviceModelObj $device, orderModelObj $order, accountModelObj $account = null): bool
    {
        if (!App::isCommissionEnabled()) {
            return true;
        }

        if (empty($account)) {
            return true;
        }

        if (settings('agent.yzshop.goods_limits.enabled') && YZShop::isInstalled()) {
            $agent = $device->getAgent();
            if ($agent) {
                $stats = Stats::total($agent);
                if ($stats && $stats['total'] >= YZShop::getRestrictGoodsTotal($agent)) {
                    return true;
                }
            }
        }

        if (App::isZeroBonusEnabled()) {
            if ($order->getExtraData('custom.zero_bonus', false)) {
                return true;
            }
        }

        $total_commission_price = $account->getCommissionPrice();

        if ($total_commission_price <= 0) {
            return true;
        }

        $agent = $device->getAgent();

        if ($agent && $agent->settings('agentData.commission.enabled')) {
            $order->setExtraData('commission.local.total', $total_commission_price);

            //可分佣的金额
            $commission_price = $account->getCommissionPrice();
            $commission_price = self::processKeeperCommissions($commission_price, $device, $order);
            if ($commission_price < 1) {
                return true;
            }

            $commission_price = self::processGSP($commission_price, $agent, $order);
            if ($commission_price < 1) {
                return true;
            }

            //还有余额则为代理佣金
            $src = $order->getBalance() > 0 ? CommissionBalance::ORDER_BALANCE : CommissionBalance::ORDER_FREE;
            $r = $agent->commission_change($commission_price, $src, ['orderid' => $order->getId()]);
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
        }

        return true;
    }

    /**
     * 处理营运人员佣金
     * @param int $commission_price
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @return int
     * @throws Exception
     */
    protected static function processKeeperCommissions(int $commission_price, deviceModelObj $device, orderModelObj $order): int
    {
        if ($commission_price <= 0) {
            return 0;
        }

        $available_price = $commission_price;

        $keepers = $device->getKeepers();
        $log = [];

        foreach ($keepers as $keeper) {
            $user = $keeper->getUser();
            if ($user) {
                list($v, $way, $is_percent) = $keeper->getCommissionValue($device);
                if ($way != Keeper::COMMISSION_ORDER) {
                    continue;
                }

                if ($is_percent) {
                    $price = intval(round($commission_price * intval($v) / 100));
                } else {
                    $price = intval($v);
                }

                if ($price > $available_price) {
                    $price = $available_price;
                }

                if ($price > 0) {
                    $r = $user->commission_change($price, CommissionBalance::GSP, ['orderid' => $order->getId()]);
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
                        throw new Exception('创建keepers佣金失败！', State::ERROR);
                    }
                }
            }
        }

        //保存分佣记录
        $order->setExtraData('commission.keepers', $log);

        return $available_price;
    }

    /**
     * 处理佣金分享用户佣金
     * @param int $commission_price
     * @param agentModelObj $agent
     * @param orderModelObj $order
     * @return int
     * @throws Exception
     */
    protected static function processGSP(int $commission_price, agentModelObj $agent, orderModelObj $order): int
    {
        $available_price = $commission_price;

        $gsp_log = [];
        $createCommission = function ($price, $user) use (&$available_price, $order, &$gsp_log) {
            if ($price > $available_price) {
                $price = $available_price;
            }
            if ($price > 0) {
                $gsp_r = $user->commission_change($price, CommissionBalance::GSP, ['orderid' => $order->getId()]);
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
                    throw new Exception('创建佣金分享失败！' . $price, State::ERROR);
                }
            }
            return true;
        };

        if ($agent->getGSPMode() == GSP::REL || $agent->getGSPMode() == GSP::FREE) {
            //获取佣金分享用户列表
            $gsp_users = $agent->getGspUsers();
            foreach ($gsp_users as &$entry) {
                if (($order->getPrice() > 0 && $entry['order']['p']) ||
                    ($order->getPrice() == 0 && $entry['order']['f'])) {
                    /** @var userModelObj $user */
                    $user = $entry['__obj'];
                    $percent = $entry['percent'];
                    if (empty($user) || $percent <= 0) {
                        continue;
                    }
                    if ($entry['type'] == 'amount') {
                        $price = intval($percent);
                    } else {
                        $price = intval(round($commission_price * $percent / 10000));
                    }
                    $more = $createCommission($price, $user);
                    if (!$more) {
                        //佣金为零，退出循环
                        break;
                    }
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
                if ($order->getPrice() > 0 && !$entry->isPayOrderIncluded()) {
                    continue;
                }
                if ($order->getPrice() == 0 && $order->getBalance() == 0 && !$entry->isFreeOrderIncluded()) {
                    continue;
                }
                $price = 0;
                if ($entry->isPercent()) {
                    $percent = intval($entry->getVal());
                    if ($percent <= 0) {
                        continue;
                    }
                    $price = intval(round($commission_price * $percent / 10000));
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
     * @param int $commission_price
     * @return int
     * @throws Exception
     */
    protected static function processProfit(deviceModelObj $device, orderModelObj $order, agentModelObj $agent, int $commission_price): int
    {
        //处理佣金分享用户
        $commission_price = self::processGSP($commission_price, $agent, $order);
        if ($commission_price < 1) {
            return 0;
        }

        //处理营运人员佣金
        return self::processKeeperCommissions($commission_price, $device, $order);
    }

    /**
     * 处理支付订单的佣金分配
     * 第1步，扣除平台费用
     * 第2步，计算商品利润（减去成本价）
     * 第3步，对利润进行佣金分配
     *       1，处理佣金分享用户
     *       2，处理营运人员佣金
     * 第4步，成本及剩余利润分配给代理商
     *
     * @param deviceModelObj $device
     * @param orderModelObj $order
     * @return bool
     * @throws Exception
     */
    protected static function pay(deviceModelObj $device, orderModelObj $order): bool
    {
        //分佣系统
        if (App::isCommissionEnabled()) {

            $agent = $device->getAgent();
            if ($agent && $agent->settings('agentData.commission.enabled')) {

                $commission_price = $order->getCommissionPrice();

                //第1步，扣除平台费用
                $commission_price = self::ProcessFee($commission_price, $agent, $order);
                if ($commission_price < 1) {
                    return true;
                }

                $order->setExtraData('commission.local.total', $commission_price);

                //第2步，计算商品利润（减去成本价）
                $goods = $order->getGoods();

                $costPrice = empty($goods) ? 0 : $goods->getCostPrice() * $order->getNum();

                $commission_price -= $costPrice;

                //第3步，对利润进行佣金分配
                if ($commission_price > 0) {
                    $commission_price = self::processProfit($device, $order, $agent, $commission_price);
                }

                //第4步，成本及剩余利润分配给代理商
                if ($goods && empty($goods->getExtraData('cw', 0))) {
                    //成本参与分佣
                    $commission_price += $costPrice;
                }

                if ($commission_price < 1) {
                    return true;
                }

                $balance = $agent->getCommissionBalance();

                $r = $balance->change($commission_price, CommissionBalance::ORDER_WX_PAY, ['orderid' => $order->getId()]);
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
            }
        }

        return true;
    }

    /**
     * 处理平台手续费
     * @param int $commission_price
     * @param agentModelObj $agent
     * @param orderModelObj $order
     * @return int
     */
    protected static function ProcessFee(int $commission_price, agentModelObj $agent, orderModelObj $order): int
    {
        $agent_data = $agent->getAgentData();
        if ($agent_data && !empty($agent_data['commission'])) {

            $fee = intval($agent_data['commission']['fee']);
            if ($fee > 0) {
                $fee_type = intval($agent_data['commission']['fee_type']);

                if ($fee_type == 0) {
                    $val = $fee;
                } else {
                    $val = intval(round($commission_price * $fee / 10000));
                }

                if ($val > $commission_price) {
                    $val = $commission_price;
                }

                //记录手续费
                $order->setExtraData('pay.fee', $val);
                $commission_price -= $val;
            }
        }

        return $commission_price;
    }
}
