<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrderReward;

defined('IN_IA') or exit('Access Denied');

use Exception;
use zovye\DBUtil;
use zovye\Job;
use zovye\Log;
use zovye\User;
use zovye\Util;
use zovye\Goods;
use zovye\Order;
use zovye\Config;
use zovye\Device;
use zovye\Helper;
use zovye\Request;
use zovye\CtrlServ;
use zovye\EventBus;
use RuntimeException;
use function zovye\err;
use function zovye\is_error;
use function zovye\settings;
use zovye\model\userModelObj;
use zovye\model\orderModelObj;
use zovye\model\deviceModelObj;

$op = Request::op('default');

$order_no = Request::str('order_no');
$device_id = Request::int('device');
$user_id = Request::int('user');
$goods_id = Request::int('goods');
$num = Request::int('num');
$ip = Request::str('ip');
$code = Request::str('code');

$log = [
    'op' => $op,
    'order_no' => $order_no,
    'device' => $device_id,
    'user' => $user_id,
    'goods' => $goods_id,
    'num' => $num,
    'ip' => $ip,
    'code' => $code,
];

$writeLog = function () use (&$log) {
    Log::debug('create_order_reward', $log);
};

if ($op == 'create_order_reward' && CtrlServ::checkJobSign([
        'order_no' => $order_no,
        'user' => $user_id,
        'device' => $device_id,
        'goods' => $goods_id,
        'num' => $num,
        'ip' => $ip,
        'code' => $code,
    ])) {

    try {
        if (empty($order_no)) {
            throw new RuntimeException('参数错误！');
        }

        $user = User::get($user_id);
        if (empty($user)) {
            throw new RuntimeException('找不到这个用户！');
        }

        $log['user'] = $user->profile();

        $reward_id = Config::app('wxapp.advs.reward.id');
        if (empty($reward_id)) {
            throw new RuntimeException('没有设置激励广告！');
        }

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            throw new RuntimeException('无法锁定用户，请稍后再试！');
        }

        $device = Device::get($device_id);
        if (empty($device)) {
            throw new RuntimeException('找不到这个设备！');
        }

        if ($code != sha1($order_no.$reward_id.$device->getShadowId().$user->getOpenid())) {
            throw new RuntimeException('不正确的请求！');
        }

        if ($num < 1) {
            throw new RuntimeException('对不起，商品数量不正确！');
        }

        $log['device'] = $device->profile();

        //锁定设备
        $retries = intval(settings('device.lockRetries', 0));
        $delay = intval(settings('device.lockRetryDelay', 1));

        if (!$device->lockAcquire($retries, $delay)) {
            if (settings('order.waitQueue.enabled', false)) {
                if (!Job::createRewardOrder([
                    'order_no' => $order_no,
                    'user' => $user->getId(),
                    'device' => $device->getId(),
                    'goods' => $goods_id,
                    'num' => $num,
                    'ip' => $ip,
                    'code' => $code,
                ])) {
                    throw new RuntimeException('启动排队任务失败！');
                }

                return true;
            }
            throw new RuntimeException('设备被占用！');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods) || empty($goods[Goods::AllowFree])) {
            throw new RuntimeException('无法兑换这个商品，请联系管理员！');
        }

        $log['goods'] = $goods;

        if ($goods['num'] < $num) {
            throw new RuntimeException('对不起，商品数量不足！');
        }

        //事件：设备已锁定
        EventBus::on('device.locked', [
            'device' => $device,
            'user' => $user,
        ]);

        if (Order::exists($order_no)) {
            throw new RuntimeException('订单已存在！');
        }

        $res = Util::checkFreeOrderLimits($user, $device);
        if (is_error($res)) {
            throw new RuntimeException($res['message']);
        }

        $orderResult = DBUtil::transactionDo(function () use ($order_no, $device, $user, $goods, $num, $ip, $code) {
            return createOrder($order_no, $device, $user, $goods, $num, $ip, $code);
        });

        if (is_error($orderResult)) {
            throw new RuntimeException($orderResult['message']);
        }

        /** @var orderModelObj $order */
        $order = $orderResult;

        //事件：出货成功，目前用于统计数据
        EventBus::on('device.openSuccess', [
            'device' => $device,
            'user' => $user,
            'order' => $order,
        ]);

        $is_pull_result_updated = false;
        $fail = 0;
        $success = 0;

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
                Log::error('create_order_reward', [
                    'orderNO' => $order->getOrderNO(),
                    'error' => $result,
                ]);
                $fail++;
            } else {
                $success++;
            }
        }

        if (empty($fail)) {
            $order->setExtraData('pull.result', [
                'errno' => 0,
                'message' => '出货完成！',
            ]);
        } else {
            $order->setExtraData(
                'pull.result',
                $success > 0 ? err('部分商品出货失败！') : err('出货失败！')
            );
        }

        $stats = $user->settings('extra.wxapp.reward.order', []);
        if (date('Ymd', $stats['time']) != date('Ymd', TIMESTAMP)) {
            $stats['total'] = 0;
        }

        $stats['time'] = time();
        $stats['total'] += $success;

        if (!$user->updateSettings('extra.wxapp.reward.order', $stats)) {
            $log['error'] = '更新用户免费记录失败！';
        }

        $order->save();
        $device->appShowMessage('出货完成，欢迎下次使用！');

    } catch (Exception $e) {
        $log['error'] = $e->getMessage();
        Log::error('create_order_reward', $log);
    }
} else {
    $log['error'] = '签名校验失败！';
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
    int $num,
    string $ip,
    string $code
): orderModelObj {
    $order_data = [
        'name' => $goods['name'],
        'goods_id' => $goods['id'],
        'src' => Order::ACCOUNT,
        'order_id' => $order_no,
        'openid' => $user->getOpenid(),
        'agent_id' => $device->getAgentId(),
        'device_id' => $device->getId(),
        'num' => $num,
        'price' => 0,
        'balance' => 0,
        'ip' => $ip,
        'extra' => [
            'payResult' => [],
            'device' => [
                'imei' => $device->getImei(),
                'name' => $device->getName(),
            ],
            'user' => $user->profile(),
            'reward' => [
                'code' => $code,
            ],
            'goods' => $goods,
        ],
        'result_code' => 0,
    ];

    $agent = $device->getAgent();
    if ($agent) {
        $order_data['extra']['agent'] = $agent->profile();
    }

    //定制功能：零佣金
    if (Helper::isZeroBonus($device, Order::FREE_STR)) {
        $order_data['agent_id'] = 0;
        $order_data['device_id'] = 0;
        $order_data['extra']['custom'] = [
            'zero_bonus' => true,
            'device' => $device->getId(),
            'agent' => $device->getAgentId(),
        ];
    }

    $order = Order::create($order_data);

    if (empty($order)) {
        throw new RuntimeException('创建订单失败！');
    }

    //事件：订单已经创建
    EventBus::on('device.orderCreated', [
        'device' => $device,
        'user' => $user,
        'order' => $order,
    ]);

    //保存在事件处理中存入订单的数据
    if (!$order->save()) {
        throw new RuntimeException('保存订单失败！');
    }

    $user->remove('last');

    return $order;
}