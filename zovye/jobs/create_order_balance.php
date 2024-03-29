<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrderBalance;

defined('IN_IA') or exit('Access Denied');

use Exception;
use RuntimeException;
use zovye\App;
use zovye\Config;
use zovye\CtrlServ;
use zovye\domain\Balance;
use zovye\domain\CommissionBalance;
use zovye\domain\Device;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\EventBus;
use zovye\ExceptionNeedsRefund;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\model\balanceModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\DBUtil;
use zovye\util\Helper;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

$order_no = Request::str('order_no');
$user_id = Request::int('user');
$device_id = Request::int('device');
$goods_id = Request::int('goods');
$num = Request::int('num');
$ip = Request::str('ip');

$log = [
    'order_no' => $order_no,
    'user' => $user_id,
    'device' => $device_id,
    'goods' => $goods_id,
    'num' => $num,
    'ip' => $ip,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$writeLog = function () use (&$log) {
    Log::debug('create_order_balance', $log);
};

$balance_id = 0;

try {
    if (!App::isBalanceEnabled()) {
        throw new RuntimeException('积分功能没有启用！');
    }

    $user = User::get($user_id);
    if (empty($user)) {
        throw new RuntimeException('找不到这个用户！');
    }

    $log['user'] = $user->profile();

    if (!$user->acquireLocker(User::BALANCE_LOCKER)) {
        throw new RuntimeException('无法锁定用户，请稍后再试！');
    }

    $device = Device::get($device_id);
    if (empty($device)) {
        throw new RuntimeException('找不到这个设备！');
    }

    if ($num < 1) {
        throw new RuntimeException('对不起，商品数量不正确！');
    }

    if (Balance::isFreeOrder()) {
        $quota = Helper::getFreeOrderLimits($user, $device);
        if ($num > $quota) {
            throw new RuntimeException('超过可用的免费额度！');
        }
    }

    $log['device'] = $device->profile();

    //锁定设备
    $retries = intval(settings('device.lockRetries', 0));
    $delay = intval(settings('device.lockRetryDelay', 1));

    if (!$device->lockAcquire($retries, $delay)) {
        if (settings('order.waitQueue.enabled', false)) {
            if (!Job::createBalanceOrder($order_no, $user, $device, $goods_id, $num, $ip)) {
                throw new RuntimeException('启动排队任务失败！');
            }

            return true;
        }
        throw new RuntimeException('设备被占用！');
    }

    $goods = $device->getGoods($goods_id);
    if (empty($goods) || empty($goods['balance'])) {
        throw new RuntimeException('无法兑换这个商品，请联系管理员！');
    }

    $log['goods'] = $goods;

    if ($goods['num'] < $num) {
        throw new RuntimeException('对不起，商品数量不足！');
    }

    $orderResult = DBUtil::transactionDo(
        function () use ($order_no, $device, $user, $goods, $num, &$balance_id, $ip) {

            $total = $goods['balance'] * $num;

            $balance = $user->getBalance();
            if ($balance->total() < $total) {
                return err('您的积分不够！');
            }

            $balance_log = $user->getBalance()->change(-$total, Balance::GOODS_EXCHANGE, [
                'user' => $user->profile(),
                'device' => $device->profile(),
                'goods' => $goods,
                'num' => $num,
                'ip' => $ip,
            ]);

            if (empty($balance_log)) {
                return err('创建积分记录失败！');
            }

            if (empty($order_no)) {
                $order_no = Order::makeUID(
                    $user,
                    $device,
                    sha1($balance_log->getId().$balance_log->getCreatetime())
                );
            }

            if (Order::exists($order_no)) {
                return err('订单已存在！');
            }

            //事件：设备已锁定
            EventBus::on(EVENT_LOCKED, [
                'device' => $device,
                'user' => $user,
            ]);

            $result = createOrder($order_no, $device, $user, $goods, $balance_log);
            if (is_error($result)) {
                return $result;
            }

            $balance_log->setExtraData('order.id', $order_no);
            if (!$balance_log->save()) {
                return err('保存订单数据失败！');
            }

            //保存balance id，后面出货失败后退积分使用
            $balance_id = $balance_log->getId();

            return $result;
        }
    );

    if (is_error($orderResult)) {
        throw new RuntimeException($orderResult['message']);
    }

    /** @var orderModelObj $order */
    $order = $orderResult;

    //事件：出货成功，目前用于统计数据
    EventBus::on(EVENT_OPEN_SUCCESS, [
        'device' => $device,
        'user' => $user,
        'order' => $order,
    ]);

    $fail = 0;
    $success = 0;

    $is_pull_result_updated = false;
    $goods['goods_id'] = $goods['id'];

    for ($i = 0; $i < $num; $i++) {
        $result = Helper::pullGoods($order, $device, $user, LOG_GOODS_BALANCE, $goods);
        if (is_error($result) || !$is_pull_result_updated) {
            $order->setResultCode($result['errno']);
            $order->setExtraData('pull.result', $result);
            if ($order->save()) {
                $is_pull_result_updated = true;
            }
        }
        if (is_error($result)) {
            Log::error('create_order_balance', [
                'orderNO' => $order->getOrderNO(),
                'error' => $result,
            ]);
            $fail++;
        } else {
            $success++;
        }
    }

    if (empty($success)) {
        ExceptionNeedsRefund::throwWith($device, '出货失败！');
    } elseif ($fail > 0) {
        $order->setExtraData('pull.result', err('部分商品出货失败！'));
        $order->save();

        ExceptionNeedsRefund::throwWithN($device, $fail, '部分商品出货失败！');
    }

    $order->setExtraData('pull.result', [
        'errno' => 0,
        'message' => '出货完成！',
    ]);

    $order->save();

    $device->appShowMessage('出货完成，欢迎下次使用！');

} catch (ExceptionNeedsRefund $e) {
    $log['error'] = $e->getMessage();
    $res = refund($balance_id, $e->getNum(), $e->getMessage());
    $log['refund'] = $res;
    if (is_error($res)) {
        Log::error('balance_refund', [
            'error' => $e->getMessage(),
            'balance_id' => $balance_id,
            'result' => $res,
        ]);
    }
} catch (Exception $e) {
    $log['error'] = $e->getMessage();
    Log::error('create_order_balance', $log);
}

Job::exit($writeLog);
/**
 * @throws Exception
 */
function createOrder(
    string $order_no,
    deviceModelObj $device,
    userModelObj $user,
    array $goods,
    balanceModelObj $balance
): orderModelObj {
    $order_data = [
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'src' => Order::BALANCE,
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'user_id' => $user->getId(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'num' => $balance->getExtraData('num'),
        'price' => 0,
        'balance' => abs($balance->getXVal()),
        'ip' => $balance->getExtraData('ip'),
        'extra' => [
            'payResult' => [],
            'device' => [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ],
            'user' => $user->profile(),
            'balance' => [
                'id' => $balance->getId(),
            ],
            'goods' => $goods,
        ],
        'result_code' => 0,
    ];

    if (App::isGDCVMachineEnabled()) {
        $order_data['extra']['CV'] = [
            'profile' => $user->getIDCardVerifiedData(),
        ];
    }

    //定制功能：零佣金
    if (Helper::isZeroBonusEnabled($device, Order::BALANCE_STR)) {
        $order_data['agent_id'] = 0;
        $order_data['device_id'] = 0;
        $order_data['extra']['custom'] = [
            'zero_bonus' => true,
            'device' => $device->getId(),
            'agent' => $device->getAgentId(),
        ];
    }

    $agent = $device->getAgent();
    if ($agent) {
        $order_data['extra']['agent'] = $agent->profile();
    }

    $order = Order::create($order_data);

    if (empty($order)) {
        throw new Exception('领取失败，创建订单失败！');
    }

    //事件：订单已经创建
    EventBus::on(EVENT_ORDER_CREATED, [
        'device' => $device,
        'user' => $user,
        'order' => $order,
        'balance' => $balance,
    ]);

    //保存在事件处理中存入订单的数据
    if (!$order->save()) {
        throw new Exception('领取失败，保存订单失败！');
    }

    $user->remove('last');
    $user->remove('donate');

    return $order;
}

function refund(int $balance_id, int $num, string $reason)
{
    $balance_log = Balance::get($balance_id);
    if (empty($balance_log)) {
        return err('找不到积分记录！');
    }

    $device = $balance_log->getDevice();
    if (empty($device)) {
        return err('找不到这个设备！');
    }

    $need = Config::balance('order.auto_rb', 0);
    if (!$need) {
        return err('设置不允许退款！');
    }

    $result = DBUtil::transactionDo(function () use ($balance_log, $num, $reason) {
        $users = $balance_log->getUser();
        if (empty($users)) {
            return err('找不到这个用户！');
        }

        $max = $balance_log->getNum();
        if ($max < 1) {
            return err('商品数量于小1！');
        }

        if (empty($num) || $num > $max) {
            $num = $max;
        }

        $goods_balance = $balance_log->getGoodsBalance();

        $x = $users->getBalance()->change($num * $goods_balance, Balance::REFUND, [
            'related' => $balance_log->getId(),
            'reason' => $reason,
        ]);

        if (empty($x)) {
            return err('退款失败！');
        }

        $balance_log->setExtraData('refund', [
            'time' => time(),
            'related' => $x->getId(),
        ]);

        if (!$balance_log->save()) {
            return err('保存数据失败！');
        }

        $order = $balance_log->getOrder();
        if ($order) {
            $users = [];
            $keeperCommissionLogs = $order->getExtraData('commission.keepers', []);
            foreach ($keeperCommissionLogs as $log) {
                $users[] = [
                    'openid' => $log['openid'],
                    'xval' => $log['xval'],
                ];
            }
            $gspCommissionLogs = $order->getExtraData('commission.gsp', []);
            foreach ($gspCommissionLogs as $log) {
                $users[] = [
                    'openid' => $log['openid'],
                    'xval' => $log['xval'],
                ];
            }
            $agentCommissionLog = $order->getExtraData('commission.agent', []);
            if ($agentCommissionLog) {
                $users[] = [
                    'openid' => $agentCommissionLog['openid'],
                    'xval' => $agentCommissionLog['xval'],
                ];
            }
            $bonusCommissionLogs = $order->getExtraData('commission.bonus', []);
            foreach ($bonusCommissionLogs as $log) {
                $users[] = [
                    'openid' => $log['openid'],
                    'xval' => $log['xval'],
                ];
            }

            $percent = floatval($num) / floatval($max);
            foreach ($users as $item) {
                $user = User::get($item['openid'], true);
                if (empty($user)) {
                    Log::error('create_order_balance', [
                        'error' => '退款，找不到这个用户！',
                        'order' => $order->getOrderNO(),
                        'data' => $item,
                    ]);
                    continue;
                }
                $val = (int)round($item['xval'] * $percent);
                if ($val > 0) {
                    $x = $user->getCommissionBalance()->change(0 - $val, CommissionBalance::ORDER_REFUND, [
                        'orderid' => $order->getId(),
                        'reason' => '出货失败，返还佣金！',
                    ]);
                    if (!$x) {
                        Log::error('create_order_balance', [
                            'error' => '退款失败！',
                            'order' => $order->getOrderNO(),
                            'user' => $user->profile(false),
                            'xval' => $val,
                        ]);
                    }
                }
            }

            $order->setExtraData('refund', [
                'message' => '出货失败！',
                'createtime' => time(),
            ]);
            $order->setRefund(Order::REFUND);
            $order->save();
        }

        return true;
    });

    if (is_error($result)) {
        $device->appShowMessage('出货失败，积分退回失败，请联系管理员！', 'error');

        return $result;
    } else {
        $device->appShowMessage('出货失败，积分已退回！', 'error');
    }

    return true;
}