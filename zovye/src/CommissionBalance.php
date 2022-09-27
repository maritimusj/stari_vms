<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;
use zovye\model\commission_balanceModelObj;

class CommissionBalance extends State
{
    const CACHE_EXPIRATION = 60 * 60;

    const PRINCIPAL_ORDER = 'order';
    const PRINCIPAL_GOODS = 'goods';

    const ORDER_FREE = 0;
    const ORDER_BALANCE = 1;
    const ORDER_WX_PAY = 2;
    const WITHDRAW = 3;

    const REFUND = 4;
    const ORDER_REFUND = 6;
    const GSP = 7;
    const BONUS = 8;
    const FEE = 9;
    const ADJUST = 10;
    const RELOAD_OUT = 11;
    const RELOAD_IN = 12;
    const RECHARGE = 13;

    const CHARGING = 20;
    const CHARGING_SF = 21;
    const CHARGING_EF = 22;

    const TRANSFER_FROM = 50;
    const TRANSFER_TO = 51;

    protected static $unknown = 'n/a';

    protected static $title = [
        self::ORDER_FREE => '免费赠送佣金',
        self::ORDER_BALANCE => '积分兑换佣金',
        self::ORDER_WX_PAY => '支付购买分成',
        self::WITHDRAW => '佣金提现',
        self::REFUND => '退款',
        self::ORDER_REFUND => '订单退款，返还佣金',
        self::GSP => '佣金分享',
        self::BONUS => '佣金奖励',
        self::FEE => '手续费',
        self::ADJUST => '管理员操作',
        self::RELOAD_OUT => '支付补货佣金',
        self::RELOAD_IN => '补货佣金收入',
        self::RECHARGE => '现金充值',
        self::CHARGING => '充电桩订单结算',
        self::CHARGING_SF => '充电桩订单(服务费)',
        self::CHARGING_EF => '充电桩订单(电费)',
        self::TRANSFER_FROM => '转账给用户',
        self::TRANSFER_TO => '收到转账',
    ];

    private $user;

    public function __construct(userModelObj $user)
    {
        $this->user = $user;
    }

    public function __toString(): string
    {
        return strval($this->total());
    }

    /**
     * 给用户打款
     * @param commission_balanceModelObj $r
     * @return bool|array
     */
    public static function MCHPay(commission_balanceModelObj $r)
    {
        $user = User::get($r->getOpenid(), true);
        if (empty($user)) {
            return error(State::ERROR, '找不到这个用户');
        }

        $n = abs($r->getXVal());

        if ($n > 0) {
            $trade_no = "r{$r->getId()}d{$r->getCreatetime()}";
            //先写数据再执行操作
            if ($r->update([
                'state' => 'mchpay',
                'trade_no' => $trade_no,
                'admin' => _W('username'),
            ], true)) {

                $res = $user->MCHPay($n, $trade_no, '佣金提现');
                if (is_error($res)) {
                    return $res;
                }

                $r->update([
                    'mchpayResult' => $res,
                ]);

                return true;
            }
        }

        return error(State::ERROR, '提现申请数据有误，请联系管理员核实！');
    }

    /**
     * @param commission_balanceModelObj $entry
     * @return array
     */
    public static function format(commission_balanceModelObj $entry): array
    {
        $data = [
            'id' => $entry->getId(),
            'xval' => number_format($entry->getXVal() / 100, 2),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        if ($entry->getXVal() > 0) {
            $data['xval'] = '+'.$data['xval'];
        }

        if ($entry->getSrc() == CommissionBalance::WITHDRAW) {

            $status = $entry->getState();

            $user = User::get($entry->getExtraData('openid'), true);
            $user_info = '';
            if ($user) {
                $user_info = "<dt>申请人</dt><dd class=\"user\"><img src=\"{$user->getAvatar()}\" alt=''/>{$user->getNickname()}</dd>";
            }
            $data['memo'] = <<<WITHDRAW
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">余额提现$status</dd>
$user_info
</dl>
WITHDRAW;
        } elseif ($entry->getSrc() == CommissionBalance::REFUND) {
            $name = $entry->getExtraData('admin');
            $admin_info = "<dt>管理员</dt><dd class=\"admin\">$name</dd>";
            $data['memo'] = <<<REFUND
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">退款</dd>
$admin_info
</dl>
REFUND;
        } elseif (in_array(
            $entry->getSrc(),
            [CommissionBalance::ORDER_FREE, CommissionBalance::ORDER_BALANCE, CommissionBalance::ORDER_WX_PAY]
        )) {

            $order_id = $entry->getExtraData('orderid');
            $data['memo'] = self::format_order_memo($order_id);

        } elseif ($entry->getSrc() == CommissionBalance::ORDER_REFUND) {

            $name = $entry->getExtraData('admin');
            $reason = $entry->getExtraData('reason');
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            $order_info = "订单ID：$order_id";
            if ($order) {
                $order_info = $order->getOrderNO();
            }
            $admin_info = empty($name) ? '' : "<dt>管理员</dt><dd class=\"admin\">$name</dd>";
            $reason_info = empty($reason) ? '' : "<dt>原因</dt><dd class=\"admin\">$reason</dd>";
            $data['memo'] = <<<ORDER_REFUND
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">订单退款，返还佣金</dd>
<dt>订单</dt>
<dd class="event">$order_info</dd>
$admin_info
$reason_info
</dl>
ORDER_REFUND;
        } elseif ($entry->getSrc() == CommissionBalance::GSP) {

            $order_id = $entry->getExtraData('orderid');
            $data['memo'] = self::format_order_memo($order_id, '佣金分享');

        } elseif ($entry->getSrc() == CommissionBalance::BONUS) {

            $order_id = $entry->getExtraData('orderid');
            $data['memo'] = self::format_order_memo($order_id, '佣金奖励');

        } elseif ($entry->getSrc() == CommissionBalance::FEE) {

            $title = '';
            if ($entry->getExtraData('refund')) {
                $title = '（已退回）';
            }
            $data['memo'] = <<<FEE
            <dl class="log dl-horizontal">
            <dt>事件</dt>
            <dd class="event">提现手续费$title</dd>
            </dl>
FEE;
        } elseif ($entry->getSrc() == CommissionBalance::ADJUST) {
            $name = $entry->getExtraData('admin');
            $admin_info = "<dt>管理员</dt><dd class=\"admin\">$name</dd>";
            $memo = $entry->getExtraData('memo');
            if ($memo) {
                $memo = "<dt>说明</dt><dd class=\"memo\">$memo</dd>";
            }
            $data['memo'] = <<<REFUND
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">管理员调整</dd>
$admin_info
$memo
</dl>
REFUND;
        } elseif ($entry->getSrc() == CommissionBalance::RELOAD_OUT) {
            $device_id = $entry->getExtraData('device');
            $keeperId = $entry->getExtraData('keeper');
            $device = Device::get($device_id);
            $device_name = $device ? $device->getName() : 'n/a';
            $device_info = "<dt>设备</dt><dd class=\"admin\">$device_name</dd>";

            $keeper = Keeper::get($keeperId);
            $keeperName = $keeper ? $keeper->getName() : 'n/a';
            $keeper_info = "<dt>营运人员</dt><dd class=\"admin\">$keeperName</dd>";

            $memo = $entry->getExtraData('memo');
            $data['memo'] = <<<REALOD_OUT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">支付补货佣金</dd>
$device_info
$keeper_info
<dt>说明</dt>
<dd class="memo">$memo</dd>
</dl>
REALOD_OUT;
        } elseif ($entry->getSrc() == CommissionBalance::RELOAD_IN) {
            $device_id = $entry->getExtraData('device');
            $device = Device::get($device_id);
            $device_name = $device ? $device->getName() : 'n/a';
            $device_info = "<dt>设备</dt><dd class=\"admin\">$device_name</dd>";
            $memo = $entry->getExtraData('memo');
            $data['memo'] = <<<REALOD_IN
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">补货佣金</dd>
$device_info
<dt>说明</dt>
<dd class="memo">$memo</dd>
</dl>
REALOD_IN;
        } elseif ($entry->getSrc() == CommissionBalance::CHARGING_SF) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            $order_info = '';
            $device_info = '';
            $group_info = '';
            if ($order) {
                $order_info = $order->getOrderNO();
                $device = $order->getDevice();
                if ($device) {
                    $device_info = "<dt>充电桩</dt><dd class=\"admin\">{$device->getName()}</dd>";
                }
                $title = $order->getExtraData('group.title', '');
                $address = $order->getExtraData('group.address', '');
                $group_info = "<dt>站点</dt><dd class=\"admin\">$title</dd><dt>地址</dt><dd class=\"admin\">$address</dd>";
            }
            $data['memo'] = <<<CHARGING
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">充电订单(服务费)</dd>
<dt>订单</dt>
<dd class="event">$order_info</dd>
$group_info
$device_info
</dl>
CHARGING;
        }
        elseif ($entry->getSrc() == CommissionBalance::CHARGING_EF) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            $order_info = '';
            $device_info = '';
            $group_info = '';
            if ($order) {
                $order_info = $order->getOrderNO();
                $device = $order->getDevice();
                if ($device) {
                    $device_info = "<dt>充电桩</dt><dd class=\"admin\">{$device->getName()}</dd>";
                }
                $title = $order->getExtraData('group.title', '');
                $address = $order->getExtraData('group.address', '');
                $group_info = "<dt>站点</dt><dd class=\"admin\">$title</dd><dt>地址</dt><dd class=\"admin\">$address</dd>";
            }
            $data['memo'] = <<<CHARGING
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">充电订单(电费)</dd>
<dt>订单</dt>
<dd class="event">$order_info</dd>
$group_info
$device_info
</dl>
CHARGING;
        }
        elseif ($entry->getSrc() == CommissionBalance::CHARGING) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            $order_info = '';
            $device_info = '';
            $group_info = '';
            if ($order) {
                $order_info = $order->getOrderNO();
                $device = $order->getDevice();
                if ($device) {
                    $device_info = "<dt>充电桩</dt><dd class=\"admin\">{$device->getName()}</dd>";
                }
                $title = $order->getExtraData('group.title', '');
                $address = $order->getExtraData('group.address', '');
                $group_info = "<dt>站点</dt><dd class=\"admin\">$title</dd><dt>地址</dt><dd class=\"admin\">$address</dd>";
            }
            $data['memo'] = <<<CHARGING
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">充电订单结算</dd>
<dt>订单</dt>
<dd class="event">$order_info</dd>
$group_info
$device_info
</dl>
CHARGING;
        } elseif ($entry->getSrc() == CommissionBalance::RECHARGE) {
            $pay_info = '';
            $pay_log_id = $entry->getExtraData('pay_log', 0);
            if ($pay_log_id > 0) {
                $pay_log = Pay::getPayLogById($pay_log_id);
                if ($pay_log) {
                    $result = $pay_log->getPayResult();
                    if (empty($result)) {
                        $result = $pay_log->getQueryResult();
                    }
                    $transaction_id = $result ? $result['transaction_id'] : '';
                    $pay_name = $pay_log->getPayName();
                    $title = Pay::getTitle($pay_name);
                    $image = MODULE_URL . "static/img/$pay_name.svg";
                    $pay_info = <<<PAY_INFO
<dt>支付</dt>
<dd class="event"><img src="$image" title="$title" style="width: 18px;height: 18px;">$transaction_id</dd>
PAY_INFO;
                }
            }

            $data['memo'] = <<<RECHARGE
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">用户充值</dd>
$pay_info
</dl>
RECHARGE;
        } elseif ($entry->getSrc() == CommissionBalance::TRANSFER_FROM) {
            $user = $entry->getExtraData('to.user', []);
            $data['memo'] = <<<TRANSFER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">余额转出</dd>
<dt>对方</dt><dd class="user"><img src="{$user['headimgurl']}" alt=''/>{$user['nickname']}</dd>
TRANSFER;
        } elseif ($entry->getSrc() == CommissionBalance::TRANSFER_TO) {
            $user = $entry->getExtraData('from.user', []);
            $data['memo'] = <<<TRANSFER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">收到转账</dd>
<dt>来自</dt><dd class="user"><img src="{$user['headimgurl']}" alt=''/>{$user['nickname']}</dd>
TRANSFER;
        }

        return $data;
    }

    /**
     * @param $id
     * @param string $addition_spec
     * @return string
     */
    protected static function format_order_memo($id, string $addition_spec = ''): string
    {
        if ($addition_spec) {
            $addition_spec = "<dt>其它</dt><dd class=\"additional\">$addition_spec</dd>";
        }

        if ($id) {
            $order = Order::get($id);
            if ($order) {

                $user = User::get($order->getOpenid(), true);
                $device = Device::get($order->getDeviceId());
                $goods = Goods::data($order->getGoodsId(), ['useImageProxy' => true]);

                if ($order->getPrice() > 0) {
                    if ($order->getExtraData('group')) {
                        $m = number_format($order->getPrice() / 100, 2);
                        $spec = "<span class=\"wxpay\">充电桩充电，支付：<span class=\"money\">￥$m</span>元</span>";
                    } else {
                        $m = number_format($order->getPrice() / 100, 2);
                        $userData = User::getUserCharacter($order->getOpenid());
                        $spec = "<span class=\"wxpay\"><img src=\"{$userData['icon']}\" title=\"{$userData['title']}\"  alt=\"\"/>{$userData['title']}<span class=\"money\">￥$m</span>元购买：{$goods['name']}x{$order->getNum()}</span>";    
                    }

                } elseif ($order->getBalance() > 0) {

                    $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                    $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                    $spec = "<span class=\"balance\">使用<span class=\"num\">{$order->getBalance()}</span>$unit_title{$balance_title}购买：{$goods['name']}x{$order->getNum()}</span>";

                } else {
                    $spec = "<span class=\"free\">免费领取：{$goods['name']}x{$order->getNum()}</span>";
                }

                $account_name = $order->getAccount();
                if ($account_name) {
                    $account_info = "<dt>公众号</dt><dd>$account_name</dd>";
                } else {
                    $account_info = '';
                }

                $device_name = $device ? $device->getName() : '<未知>';
                $user_avatar = $user ? $user->getAvatar() : '';
                $user_name = $user ? $user->getNickname() : '<未知>';
                $memo = <<<ORDER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">$spec</dd>
<dt>设备</dt>
<dd class="device">$device_name</dd>
$account_info
<dt>用户</dt>
<dd class="user"><img src="$user_avatar" alt=""/>$user_name</dd>
$addition_spec
</dl>
ORDER;
            } else {
                $memo = <<<ORDER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event"><未知></dd>
<dt>设备</dt>
<dd class="device"><未知></dd>
$addition_spec
</dl>
ORDER;
            }

            return $memo;
        }

        return "";
    }


    /**
     * 获取当前余额
     * @return int
     */
    public function total(): int
    {
        $total = 0;

        if ($this->user) {

            $openid = $this->user->getOpenid();
            $query = CommissionBalance::query(['openid' => $openid]);

            $last_id = 0;
            $last_total = 0;
            $last_time = 0;

            $cache = $this->user->get('commission_balance', []);
            if ($cache && isset($cache['id']) && isset($cache['total'])) {

                $last_id = intval($cache['id']);
                $last_total = intval($cache['total']);
                $last_time = intval($cache['time']);

                $query->where(['id >' => $last_id]);
            }

            if (time() - $last_time > self::CACHE_EXPIRATION) {

                list($total, $id) = $query->get(['sum(x_val)', 'max(id)']);

                if (isset($id) && $id > $last_id) {
                    $total += $last_total;
                    $locker = $this->user->acquireLocker(User::COMMISSION_BALANCE_LOCKER);
                    if ($locker) {
                        $this->user->set('commission_balance', [
                            'id' => $id,
                            'total' => $total,
                            'time' => time(),
                        ]);
                        $locker->unlock();
                    }
                } else {
                    $total = $last_total;
                }

            } else {
                $total = $query->get('sum(x_val)') + $last_total;
            }
        }

        return $total;
    }

    /**
     * 返回用户余额变动记录
     */
    public function log(): ?base\modelObjFinder
    {
        if ($this->user) {
            $openid = $this->user->getOpenid();

            return CommissionBalance::query(['openid' => $openid]);
        }

        return null;
    }

    /**
     * 余额变动操作
     * @param int $val
     * @param int $src
     * @param array $extra
     * @return commission_balanceModelObj
     */
    public function change(int $val, int $src, array $extra = []): ?commission_balanceModelObj
    {
        if ($this->user) {
            return m('commission_balance')->create(
                We7::uniacid(
                    [
                        'openid' => $this->user->getOpenid(),
                        'src' => $src,
                        'x_val' => $val,
                        'extra' => serialize($extra),
                    ]
                )
            );
        }

        return null;
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return m('commission_balance')->where(We7::uniacid([]))->where($condition);
    }

    public static function findOne($condition = []): ?commission_balanceModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function getFirstCommissionBalance(userModelObj $user): ?commission_balanceModelObj
    {
        $id = $user->settings('extra.first.commission.id');
        if ($id) {
            return self::findOne(['id' => $id]);
        }
        $query = self::query(['openid' => $user->getOpenid()]);
        /** @var commission_balanceModelObj $log */
        $log = $query->orderBy('id ASC')->findOne();
        if ($log) {
            $user->updateSettings('extra.first.commission', [
                'id' => $log->getId(),
            ]);

            return $log;
        }

        return null;
    }

    public static function getFirstCommissionBalanceOf(userModelObj $user, $src): ?commission_balanceModelObj
    {
        $id = $user->settings("extra.first.commission_$src.id");
        if ($id) {
            return self::findOne(['id' => $id]);
        }
        $query = self::query(['openid' => $user->getOpenid(), 'src' => $src]);
        /** @var commission_balanceModelObj $log */
        $log = $query->orderBy('id ASC')->findOne();
        if ($log) {
            $user->updateSettings("extra.first.commission_$src", [
                'id' => $log->getId(),
            ]);

            return $log;
        }

        return null;
    }
}
