<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\App;
use zovye\base;
use zovye\business\VIP;
use zovye\model\commission_balanceModelObj;
use zovye\model\pay_logsModelObj;
use zovye\model\userModelObj;
use zovye\Pay;
use zovye\State;
use zovye\util\DBUtil;
use zovye\We7;
use function zovye\_W;
use function zovye\err;
use function zovye\is_error;
use function zovye\m;
use function zovye\settings;

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

    const CHARGING_FEE = 20;
    const CHARGING_SERVICE_FEE = 21;
    const CHARGING_ELECTRIC_FEE = 22;
    const CHARGING_BONUS = 23;

    const FUELING_FEE = 30;

    const TRANSFER_OUT = 50;
    const TRANSFER_RECEIVED = 51;

    const APP_ONLINE_BONUS = 60;
    const DEVICE_QOE_BONUS = 61;

    protected static $unknown = 'n/a';

    protected static $title = [
        self::ORDER_FREE => '免费领取佣金',
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
        self::CHARGING_FEE => '充电桩订单结算',
        self::CHARGING_SERVICE_FEE => '充电桩订单(服务费)',
        self::CHARGING_ELECTRIC_FEE => '充电桩订单(电费)',
        self::CHARGING_BONUS => '停车补贴',
        self::FUELING_FEE => '尿素加注订单结算',
        self::TRANSFER_OUT => '转账给用户',
        self::TRANSFER_RECEIVED => '收到转账',
        self::APP_ONLINE_BONUS => 'APP在线奖励',
        self::DEVICE_QOE_BONUS => '设备电费佣金',
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
            return err('找不到这个用户');
        }

        $n = abs($r->getXVal());

        if ($n > 0) {
            $trade_no = $r->getExtraData('trade_no', '');
            if (empty($trade_no)) {
                $trade_no = "r{$r->getId()}d{$r->getCreatetime()}";
            }
            //先写数据再执行操作
            if ($r->update([
                'state' => 'mchpay',
                'trade_no' => $trade_no,
                'admin' => _W('username'),
            ], true)) {

                $res = Pay::MCHPay($user, $n, $trade_no, '帐户余额提现');
                if (is_error($res)) {
                    return $res;
                }

                $r->update([
                    'mchpayResult' => $res,
                ]);

                return true;
            }
        }

        return err('提现申请数据有误，请联系管理员核实！');
    }

    public static function queryMCHPayResult(commission_balanceModelObj $entry)
    {
        $MCHPayResult = $entry->getExtraData('mchpayResult');
        if ($MCHPayResult['payment_no'] || $MCHPayResult['detail_status'] == 'SUCCESS' || $MCHPayResult['detail_status'] == 'FAIL') {
            return $MCHPayResult;
        }

        if ($MCHPayResult['batch_id']) {
            $user = User::get($entry->getOpenid(), true);
            if ($user) {
                $result = Pay::getMCHPayResult($MCHPayResult['batch_id'], $MCHPayResult['out_batch_no']);
                if ($result['detail_status'] == 'SUCCESS' || $result['detail_status'] == 'FAIL') {
                    $MCHPayResult['detail_status'] = $result['detail_status'];
                    $entry->update(['mchpayResult' => $MCHPayResult]);
                    $entry->save();
                }
            }
        }

        return $MCHPayResult;
    }

    public static function recharge(userModelObj $user, pay_logsModelObj $pay_log)
    {
        if (!$pay_log->isPaid()) {
            return err('未支付完成！');
        }

        if ($pay_log->isRecharged()) {
            return err('支付记录已使用！');
        }

        if ($pay_log->isCancelled() || $pay_log->isTimeout() || $pay_log->isRefund()) {
            return err('支付已无效!');
        }

        return DBUtil::transactionDo(function () use ($user, $pay_log) {

            $price = $pay_log->getPrice();
            if ($price < 1) {
                return err('支付金额小于1!');
            }

            if (App::isFuelingDeviceEnabled()) {
                $promotion_price = VIP::getRechargePromotionVal($price);
            }

            $extra = [
                'pay_log' => $pay_log->getId(),
            ];

            if (isset($promotion_price) && $promotion_price != 0) {
                $extra['promotion_price'] = $promotion_price;
                $price += $promotion_price;
            }

            $balance = $user->getCommissionBalance();
            if (!$balance->change($price, CommissionBalance::RECHARGE, $extra)) {
                return err('创建用户账户记录失败!');
            }

            $pay_log->setData('recharged', [
                'time' => time(),
            ]);

            if (!$pay_log->save()) {
                return err('保存用户数据失败!');
            }

            return true;
        });
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
            [CommissionBalance::ORDER_FREE, CommissionBalance::ORDER_BALANCE, CommissionBalance::ORDER_WX_PAY],
            true
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
            $keeper_info = "<dt>运营人员</dt><dd class=\"admin\">$keeperName</dd>";

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
        } elseif ($entry->getSrc() == CommissionBalance::CHARGING_SERVICE_FEE) {
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
        } elseif ($entry->getSrc() == CommissionBalance::CHARGING_ELECTRIC_FEE) {
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
        } elseif ($entry->getSrc() == CommissionBalance::CHARGING_FEE) {
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
        } elseif ($entry->getSrc() == CommissionBalance::CHARGING_BONUS) {
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
<dd class="event">停车补贴</dd>
<dt>订单</dt>
<dd class="event">$order_info</dd>
$group_info
$device_info
</dl>
CHARGING;
        } elseif ($entry->getSrc() == CommissionBalance::FUELING_FEE) {
            $order_id = $entry->getExtraData('orderid');
            $order = Order::get($order_id);
            $order_info = '';
            $device_info = '';
            $goods_info = '';
            if ($order) {
                $order_info = $order->getOrderNO();
                $device = $order->getDevice();
                if ($device) {
                    $device_info = "<dt>设备</dt><dd class=\"admin\">{$device->getName()}</dd>";
                }
                $goods = $order->getGoodsData();
                if ($goods) {
                    $num = number_format($order->getNum(), 2, '.', '');
                    $goods_info = "<dt>商品</dt><dd class=\"admin\">{$goods['name']} x <span style='color:#2196f3;'>$num</span>{$goods['unit_title']}</dd>";
                }
            }
            $data['memo'] = <<<CHARGING
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">尿素加注订单结算</dd>
<dt>订单</dt>
<dd class="event">$order_info</dd>
$device_info
$goods_info
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
                    $image = MODULE_URL."static/img/$pay_name.svg";
                    $pay_info = <<<PAY_INFO
<dt>支付</dt>
<dd class="event"><img src="$image" title="$title" style="width: 18px;height: 18px;" alt="">$transaction_id</dd>
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
        } elseif ($entry->getSrc() == CommissionBalance::TRANSFER_OUT) {
            $user = $entry->getExtraData('to.user', []);
            $data['memo'] = <<<TRANSFER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">余额转出</dd>
<dt>对方</dt><dd class="user"><img src="{$user['headimgurl']}" alt=''/>{$user['nickname']}</dd>
TRANSFER;
        } elseif ($entry->getSrc() == CommissionBalance::TRANSFER_RECEIVED) {
            $user = $entry->getExtraData('from.user', []);
            $data['memo'] = <<<TRANSFER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">收到转账</dd>
<dt>来自</dt><dd class="user"><img src="{$user['headimgurl']}" alt=''/>{$user['nickname']}</dd>
TRANSFER;
        } elseif ($entry->getSrc() == CommissionBalance::APP_ONLINE_BONUS) {
            $device_id = $entry->getExtraData('device', 0);
            $device = Device::get($device_id);
            if ($device) {
                $device_info = "<dt>设备</dt><dd class=\"admin\">{$device->getName()}</dd>";
            } else {
                $device_info = "<dt>设备</dt><dd class=\"admin\"><已删除></dd>";
            }
            $b_ts = $entry->getExtraData('b', 0);
            $begin = $b_ts ? date('Y-m-d H:i:s', $b_ts) : '?';
            $e_ts = $entry->getExtraData('e', 0);
            $end = $e_ts ? date('Y-m-d H:i:s', $e_ts) : '?';
            $memo = "<dt>区间</dt><dd class=\"admin\">$begin ~ $end</dd>";
            $data['memo'] = <<<TRANSFER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">APP在线奖励</dd>
{$device_info}
{$memo}
TRANSFER;
        } elseif ($entry->getSrc() == CommissionBalance::DEVICE_QOE_BONUS) {
            $device_id = $entry->getExtraData('device', 0);
            $device = Device::get($device_id);
            if ($device) {
                $device_info = "<dt>设备</dt><dd class=\"admin\">{$device->getName()}</dd>";
            } else {
                $device_info = "<dt>设备</dt><dd class=\"admin\"><已删除></dd>";
            }
            $kw = $entry->getExtraData('kw', 0);
            $memo = "<dt>电量</dt><dd class=\"admin\">{$kw}Kw</dd>";
            $data['memo'] = <<<TRANSFER
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">设备电费佣金</dd>
{$device_info}
{$memo}
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
            $addition_spec = "<dt>备注</dt><dd class=\"additional\">$addition_spec</dd>";
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
                        $spec = "<span class=\"wxpay\">充电桩充电，支付：<span class=\"money\">$m</span>元</span>";
                    } else {
                        $m = number_format($order->getPrice() / 100, 2);
                        $userData = User::getUserCharacter($order->getOpenid());
                        $spec = "<span class=\"wxpay\"><img src=\"{$userData['icon']}\" title=\"{$userData['title']}\"  alt=\"\"/>{$userData['title']}<span class=\"money\">$m</span>元购买：{$goods['name']}x{$order->getNum()}</span>";
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
    public function log(): ?base\ModelObjFinder
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

    public static function query($condition = []): base\ModelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('commission_balance')->where($condition);
        }

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
