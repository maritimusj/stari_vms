<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTimeImmutable;
use zovye\Account;
use zovye\App;
use zovye\model\commission_balanceModelObj;
use zovye\CommissionBalance;
use zovye\Device;
use zovye\Goods;
use zovye\Request;
use zovye\Job;
use zovye\Order;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

class balance
{
    /**
     * 佣金统计
     *
     * @return array
     */
    public static function brief(): array
    {
        $result = [
            'balance' => [
                'total' => 0,
                'today' => 0,
                'month' => 0,
                'all' => 0,
            ],
        ];

        if (!common::checkCurrentUserPrivileges('F_cm', true)) {
            return $result;
        }

        $user = common::getAgentOrPartner();

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if (App::isCommissionEnabled() && $agent) {
            $agent_data = $agent->getAgentData();
            if ($agent_data['commission']['enabled']) {
                //余额
                $result['balance']['total'] = number_format($agent->getCommissionBalance()->total() / 100, 2, '.', '');
                $condition = [
                    'openid' => $agent->getOpenid(),
                    'x_val >' => 0,
                    'src <>' => CommissionBalance::REFUND, //退款不计算在收益中
                ];

                //总收益
                $result['balance']['all'] = number_format(
                    CommissionBalance::query($condition)->get('sum(x_val)') / 100,
                    2,
                    '.',
                    ''
                );
                //今日收入
                $condition['createtime >='] = (new DateTimeImmutable('00:00'))->getTimestamp();
                $result['balance']['today'] = number_format(
                    CommissionBalance::query($condition)->get('sum(x_val)') / 100,
                    2,
                    '.',
                    ''
                );
                //本月收入
                $condition['createtime >='] = (new DateTimeImmutable('first day of this month 00:00'))->getTimestamp();
                $result['balance']['month'] = number_format(
                    CommissionBalance::query($condition)->get('sum(x_val)') / 100,
                    2,
                    '.',
                    ''
                );
            }
        }

        return $result;
    }

    /**
     * @param $user userModelObj
     * @param int $amount
     * @param string $memo
     * @param mixed $extra
     * @return array
     */
    public static function balanceWithdraw(userModelObj $user, int $amount, string $memo = '', $extra = []): array
    {
        //先锁定用户，防止恶意重复提交
        if (!$user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
            return err('锁定用户失败，请重试！');
        }

        if ($user->isBanned()) {
            return err('用户暂时无法提现！');
        }

        if ($user->isBusy()) {
            return err('请等待全部订单结算完成后再试！');
        }

        if ($amount < 1) {
            return err('提现金额必须大于0！');
        }

        $balance = $user->getCommissionBalance();
        if ($amount > $balance->total()) {
            return err('账户可用余额不足！');
        }

        $withdraw = settings('commission.withdraw', []);

        if (!empty($withdraw['count']['month'])) {
            $count = CommissionBalance::query(
                [
                    'src' => CommissionBalance::WITHDRAW,
                    'openid' => $user->getOpenid(),
                    'createtime >=' => (new DateTimeImmutable('first day of this month 00:00'))->getTimestamp(),
                ]
            )->count();

            if ($count >= $withdraw['count']['month']) {
                return err('本月可用提现次数已用完！');
            }
        }

        if (!empty($withdraw['min']) && $amount < $withdraw['min']) {
            $min = number_format($withdraw['min'] / 100, 2);

            return err("提现金额不能少于{$min}元");
        }

        if (!empty($withdraw['max']) && $amount > $withdraw['max']) {
            $max = number_format($withdraw['max'] / 100, 2);

            return err("提现金额不能大于{$max}元");
        }

        $res = Util::transactionDo(function () use ($amount, $memo, $balance, $user, $extra) {
                //计算手续费
                $fee = 0;
                $config = settings('commission.withdraw.fee', []);
                if ($config) {
                    if (isset($config['permille'])) {
                        $ratio = intval($config['permille']);
                    } else {
                        $ratio = intval($config['percent']) * 10;
                    }

                    if ($ratio > 0) {
                        $fee = intval(round($amount * $ratio / 1000));

                        if (!empty($config['min']) && $fee < $config['min']) {
                            $fee = intval($config['min']);
                        }

                        if (!empty($config['max']) && $fee > $config['max']) {
                            $fee = intval($config['max']);
                        }
                    }
                }

                $balance_total = $balance->total();
                $fee_rec = null;

                if ($fee > 0) {
                    //尽量从提现后的余额中扣除手续费，余额不够的话减少提现金额
                    if ($fee + $amount > $balance_total) {
                        $amount -= ($fee + $amount - $balance_total);
                        if ($amount <= 0) {
                            return err('扣除手续费后提现金额为零！');
                        }
                    }

                    $fee_rec = $balance->change(-$fee, CommissionBalance::FEE);
                    if (empty($fee_rec)) {
                        return err('创建手续费失败！');
                    }
                }

                //整额提现
                $times = settings('commission.withdraw.times', 0);
                if ($times > 0 && $amount % ($times * 100) > 0) {
                    return err("提现金额必须是{$times}的整倍数！");
                }

                $r = $balance->change(
                    -$amount,
                    CommissionBalance::WITHDRAW,
                    array_merge($extra, [
                        'openid' => $user->getOpenid(),
                        'mobile' => $user->getMobile(),
                        'ip' => CLIENT_IP,
                        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
                        'current' => $balance_total,
                        'remain' => $balance_total - $amount - $fee,
                        'fee' => $fee,
                        'memo' => $memo,
                    ])
                );

                if (empty($r)) {
                    return err('创建提现数据失败！');
                }

                if ($fee_rec) {
                    if (!$r->update(
                        [
                            'gcr' => [$fee_rec->getId()],
                        ]
                    )) {
                        return err('更新提现手续费数据失败！');
                    }

                    if (!$fee_rec->update(
                        [
                            'openid' => $user->getOpenid(),
                            'gid' => $r->getId(), //gid => ground id,相关联的记录以主纪录ＩＤ为组ＩＤ
                        ]
                    )) {
                        return err('更新手续费数据失败！');
                    }
                }

                $msg = '提现申请提交成功，请等待管理员审核！';

                Job::withdraw($user->getId(), $amount);

                //自动打款
                if (settings('commission.withdraw.pay_type') == WITHDRAW_SYS) {
                    $result = CommissionBalance::MCHPay($r);
                    if (is_error($result)) {
                        return err('自动打款失败，请联系管理员！');
                    } else {
                        $msg = '成功，提现已经完成，请注意确认收款！';
                    }
                }

                return ['message' => $msg];
            }
        );

        if (is_error($res)) {
            return $res;
        }

        return [
            'balance' => $balance->total(),
            'msg' => $res['message'],
        ];
    }

    /**
     * 提现.
     *
     * @return array
     */
    public static function withdraw(): array
    {
        common::checkCurrentUserPrivileges('F_cm');

        $user = common::getAgentOrPartner();

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if ($agent) {
            if (!empty(settings('commission.withdraw.bank_card'))) {
                if (empty($agent->settings('agentData.bank'))) {
                    return err('请先绑定银行卡！');
                }
            }

            if ($agent->isPaymentConfigEnabled()) {
                return err('提现申请被拒绝，请联系管理员！');
            }

            return balance::balanceWithdraw($agent, Request::float('amount', 0, 2) * 100);
        }

        return err('提现失败，请联系客服！');
    }

    /**
     * @param userModelObj $user
     * @param string $type
     * @param int $page
     * @param int $page_size
     *
     * @return array
     */
    public static function getUserBalanceLog(
        userModelObj $user,
        string $type,
        int $page = 0,
        int $page_size = DEFAULT_PAGE_SIZE
    ): array {
        $page = max(1, $page);
        $page_size = !empty($page_size) ? max(1, $page_size) : DEFAULT_PAGE_SIZE;

        $balance = $user->getCommissionBalance();
        $query = $balance->log();

        if ($type == 'incr') {
            $query->where(['x_val >' => 0]);
        } elseif ($type == 'decr') {
            $query->where(['x_val <' => 0]);
        }

        $total = $query->count();
        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var commission_balanceModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'title' => CommissionBalance::desc($entry->getSrc()),
                    'xval' => number_format($entry->getXVal() / 100, 2),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
                if ($entry->getXVal() > 0) {
                    $data['xval'] = '+'.$data['xval'];
                }
                if ($entry->getSrc() == CommissionBalance::WITHDRAW) {
                    $status = $entry->getState();
                    $data['title'] .= $status;
                    $user = User::get($entry->getExtraData('openid'), true);
                    if ($user) {
                        $data['memo'] = "申请人：{$user->getName()}";
                    }
                }
                if ($entry->getSrc() == CommissionBalance::FEE) {
                    if ($entry->getExtraData('refund')) {
                        $data['title'] .= '（已退回）';
                    }
                } else {
                    $order_id = $entry->getExtraData('orderid');
                    if ($order_id) {
                        $order = Order::get($order_id);
                        if ($order) {
                            $data['orderid'] = $order_id;
                            $user = User::get($order->getOpenid(), true);
                            $device = Device::get($order->getDeviceId());
                            if ($order->getPrice() > 0) {
                                $type = User::getUserCharacter($user)['title'];
                                $m = number_format($order->getPrice() / 100, 2);
                                $spec = "{$type}付款￥{$m}元购买";
                            } else {
                                $spec = '免费领取';
                            }

                            $account_name = $order->getAccount();
                            if ($account_name) {
                                $account = Account::findOneFromName($account_name);
                                if ($account) {
                                    $account_info = "通过公众号“{$account->getTitle()}”，";
                                } else {
                                    $account_info = "通过公众号 “{$account_name}”，";
                                }
                            } else {
                                $account_info = '';
                            }
                            $goods = Goods::get($order->getGoodsId());
                            if ($goods) {
                                $data['goods'] = Goods::format($goods, false, true);
                            }
                            if ($device) {
                                $data['device'] = [
                                    'name' => $device->getName(),
                                    'uid' => $device->getImei(),
                                    'id' => $device->getId(),
                                ];
                            }
                            $device_name = $device ? $device->getName() : '<未知设备>';
                            $subtitle = '佣金';
                            if ($entry->getSrc() == CommissionBalance::BONUS) {
                                $subtitle = '奖励';
                            } elseif ($entry->getSrc() == CommissionBalance::GSP) {
                                $subtitle = '分成';
                            }

                            if ($entry->getExtraData('refund')) {
                                $subtitle .= '（已退回）';
                            }

                            $username = $user ? $user->getNickname() : '未知';
                            $data['memo'] = "<$username>{$account_info}在设备[ $device_name ]上{$spec}，获得{$subtitle}！";
                        }
                    }
                }

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 记录.
     *
     * @return array
     */
    public static function log(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_cm');

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if ($agent) {
            return balance::getUserBalanceLog(
                $agent,
                Request::str('type'),
                Request::int('page'),
                Request::int('pagesize')
            );
        }

        return err('获取列表失败！');
    }

    public static function userBalanceLog(): array
    {
        $user = agent::getUserByGUID(Request::str('guid'));
        if ($user) {

            $type = Request::str('type');
            $page = Request::int('page');
            $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

            $page = max(1, $page);
            $page_size = max(1, $page_size);

            return self::getUserBalanceLog($user, $type, $page, $page_size);
        }

        return err('获取列表失败！');
    }
}