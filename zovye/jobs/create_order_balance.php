<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrderBalance;

use Exception;
use RuntimeException;
use zovye\App;
use zovye\Balance;
use zovye\CtrlServ;
use zovye\Device;
use zovye\EventBus;
use zovye\ExceptionNeedsRefund;
use zovye\Helper;
use zovye\Job;
use zovye\model\balanceModelObj;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\request;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;

$op = request::op('default');
$balance_id = request::int('balance');
$log = [
    'op' => $op,
    'balance_id' => $balance_id,
];

$writeLog = function () use (&$log) {
    Util::logToFile('create_order_balance', $log);
};

if ($op == 'create_order_balance' && CtrlServ::checkJobSign(['balance' => $balance_id])) {

    try {
        if (!App::isBalanceEnabled()) {
            throw new RuntimeException('积分功能没有启用！');
        }

        $balance = Balance::get($balance_id);
        if (empty($balance)) {
            throw new RuntimeException('找不到积分记录！');
        }

        $user = $balance->getUser();
        if (empty($user)) {
            throw new RuntimeException('找不到这个用户！');
        }

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            throw new RuntimeException('用户无法锁定！');
        }

        //判断订单是否存在一定要在锁定用户成功后判断
        $order_no = $balance->getExtraData('order');
        if ($order_no && Order::exists($order_no)) {
            throw new RuntimeException('订单已经完成！');
        }

        $device = $balance->getDevice();
        if (empty($device)) {
            throw new RuntimeException('找不到这个设备！');
        }

        $num = $balance->getNum();
        if ($num < 1) {
            throw new RuntimeException('商品数量于小1！');
        }

        $goods_id = $balance->getGoodsId();
        $goods = $device->getGoods($goods_id);
        if (empty($goods)) {
            ExceptionNeedsRefund::throwWith($device, '无法兑换这个商品！');
        }

        if ($goods['num'] < $num) {
            ExceptionNeedsRefund::throwWith($device, '商品库存不够！');
        }

        //锁定设备
        $retries = intval(settings('device.lockRetries', 0));
        $delay = intval(settings('device.lockRetryDelay', 1));

        if (!$device->lockAcquire($retries, $delay)) {
            if (settings('order.waitQueue.enabled', false)) {
                if (!Job::createBalanceOrder($balance)) {
                    ExceptionNeedsRefund::throwWith($device, '启动排队任务失败！');
                }
                return true;
            }
            ExceptionNeedsRefund::throwWith($device, '设备被占用！');
        }

        //事件：设备已锁定
        EventBus::on('device.locked', [
            'device' => $device,
            'user' => $user,
        ]);

        if (empty($order_no)) {
            $order_no = Order::makeUID($user, $device, sha1($balance->getId() . $balance->getCreatetime()));
            $balance->setExtraData('order', $order_no);
            $balance->save();
        }

        $orderResult = Util::transactionDo(function () use ($order_no, $device, $user, $goods, $balance) {
            return createOrder($order_no, $device, $user, $goods, $balance);
        });

        if (is_error($orderResult)) {
            ExceptionNeedsRefund::throwWith($device, $orderResult['message']);
        } else {
            /** @var orderModelObj $order */
            $order = $orderResult;

            //事件：出货成功，目前用于统计数据
            EventBus::on('device.openSuccess', [
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
                    Util::logToFile('create_order_balance', [
                        'orderNO' => $order_no,
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

            $order->setExtraData('pull.result', ['message' => '出货完成！']);
            $order->save();

            $device->appShowMessage('出货完成，欢迎下次使用！');
        }

    } catch (ExceptionNeedsRefund $e) {
        $log['error'] = $e->getMessage();
        refund($balance_id, $e->getNum(), $e->getMessage());
    } catch (Exception $e) {
        $log['error'] = $e->getMessage();
    }

    Job::exit($writeLog);
}

/**
 * @throws Exception
 */
function createOrder(string          $order_no,
                     deviceModelObj  $device,
                     userModelObj    $user,
                     array           $goods,
                     balanceModelObj $balance): orderModelObj
{
    $order_data = [
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'src' => Order::BALANCE,
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
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

    $agent = $device->getAgent();
    if ($agent) {
        $order_data['extra']['agent'] = $agent->profile();
    }

    $order = Order::create($order_data);

    if (empty($order)) {
        throw new Exception('领取失败，创建订单失败！');
    }

    //事件：订单已经创建
    EventBus::on('device.orderCreated', [
        'device' => $device,
        'user' => $user,
        'order' => $order,
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
    $balance = Balance::get($balance_id);
    if (empty($balance)) {
        return err('找不到积分记录！');
    }

    $device = $balance->getDevice();
    if (empty($device)) {
        return err('找不到这个设备！');
    }

    $need = Helper::NeedAutoRefund($device);
    if ($need) {
        $result = Util::transactionDo(function () use ($balance, $num, $device, $reason) {
            $user = $balance->getUser();
            if (empty($user)) {
                return err('找不到这个用户！');
            }

            $max = $balance->getNum();
            if ($max < 1) {
                return err('商品数量于小1！');
            }

            if (empty($num) || $num > $max) {
                $num = $max;
            }

            $goods_balance = $balance->getGoodsBalance();

            $x = $user->getBalance()->change($num * $goods_balance, Balance::REFUND, [
                'related' => $balance->getId(),
                'reason' => $reason,
            ]);

            if (empty($x)) {
                return err('退款失败！');
            }

            $balance->setExtraData('refund', [
                'time' => time(),
                'related' => $x->getId(),
            ]);

            if (!$balance->save()) {
                return err('保存数据失败！');
            }
            return $x;
        });

        if (is_error($result)) {
            $device->appShowMessage('出货失败，积分退回失败，请联系管理员！', 'error');
        } else {
            $device->appShowMessage('出货失败，积分已退回！', 'error');
        }

        return true;
    }

    return err('设置不允许退款！');
}