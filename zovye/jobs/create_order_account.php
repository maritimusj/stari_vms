<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\createOrderAccount;

defined('IN_IA') or exit('Access Denied');

use Exception;
use zovye\Account;
use zovye\CtrlServ;
use zovye\Device;
use zovye\DeviceUtil;
use zovye\Goods;
use zovye\Job;
use zovye\JobException;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Request;
use zovye\State;
use zovye\User;
use zovye\ZovyeException;
use function zovye\is_error;
use function zovye\settings;

$account_id = Request::str('account');
$device_id = Request::str('device');
$user_id = Request::str('user');
$goods_id = Request::str('goods');
$order_uid = Request::str('orderUID');
$ignore_goods_num = Request::int('ignoreGoodsNum');
$ip = Request::str('ip');

$log = [
    'account' => $account_id,
    'device' => $device_id,
    'user' => $user_id,
    'goods' => $goods_id,
    'orderUID' => $order_uid,
    'ignoreGoodsNum' => $ignore_goods_num,
    'ip' => $ip,
];

if (Request::has('tk_order_no')) {
    $log['tk_order_no'] = Request::str('tk_order_no');
}

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

try {
    /** @var deviceModelObj $device */
    $device = Device::get($device_id);
    if (empty($device)) {
        ZovyeException::throwWith('找不到指定的设备:'.$device_id, -1);
    }

    /** @var userModelObj $user */
    $user = User::get($user_id);
    if (empty($user) || $user->isBanned()) {
        ZovyeException::throwWith('找不到指定的用户或者已禁用!', -1, $device);
    }

    if (!$user->acquireLocker(User::ORDER_ACCOUNT_LOCKER)) {
        ZovyeException::throwWith('用户锁定失败!', -1, $device);
    }

    $account = Account::get($account_id);
    if (empty($account)) {
        ZovyeException::throwWith('找不到指定公众号！', -1, $device);
    }

    if (!empty($order_uid)) {
        $order = Order::get($order_uid, true);
        if ($order) {
            if ($order->getResultCode() == 0) {
                ZovyeException::throwWith('订单已经存在！', -1, $device);
            }

            $max_retries = intval(settings('order.retry.max', 0));
            if (!empty($max_retries)) {
                $total = intval($order->getExtraData('retry.total', 0));

                if ($total >= $max_retries) {
                    ZovyeException::throwWith('已超过最大重试次数！', -1, $device);
                }

                $order->setExtraData('retry.total', $total + 1);
                if (!$order->save()) {
                    ZovyeException::throwWith('订单数据无法保存！', -1, $device);
                }
            }
        }
    }

    if (empty($goods_id)) {
        /**
         * 依次判断前10个货道上的商品是否为免费商品并且数量大于0
         */
        for ($i = 0; $i < 10; $i++) {
            $goods = $device->getGoodsByLane($i);
            if (empty($goods) || !$goods[Goods::AllowFree]) {
                continue;
            }
            if ($goods['num'] < 1) {
                $goods = $device->getGoods($goods['id']);
            }
            if ($goods && $goods['num'] > 0) {
                break;
            }
        }
    } else {
        $goods = $device->getGoods($goods_id);
    }

    if (empty($goods)) {
        ZovyeException::throwWith('找不到商品！', -1, $device);
    }

    if (empty($ignore_goods_num) && $goods['num'] < 1) {
        ZovyeException::throwWith('商品数量不足！', -1, $device);
    }

    $data = [
        'online' => false,
        'level' => empty($order) ? LOG_GOODS_CB : LOG_GOODS_RETRY,
        'goodsId' => $goods['id'],
        'ip' => empty($ip) ? $user->getLastActiveIp() : $ip,
        $device,
        $user,
        $account,
    ];

    if (!empty($order_uid)) {
        $data['orderId'] = $order_uid;
    }

    if ($log['tk_order_no']) {
        $data['tk_order_no'] = $log['tk_order_no'];
    }

    if ($ignore_goods_num) {
        $data['ignoreGoodsNum'] = true;
    }

    if (!empty($order)) {
        $data[] = $order;
    }

    try {
        $result = DeviceUtil::open($data);
        if (is_error($result)) {
            if ($result['errno'] === State::ERROR_LOCK_FAILED && settings('order.waitQueue.enabled', false)) {
                if (!Job::createAccountOrder($log)) {
                    throw new Exception('启动排队任务失败！');
                }

                $log['message'] = '重新排队中';
                return true;
            }
            throw new Exception($result['message']);
        }

        $log['result'] = $result;

    } catch (Exception $e) {
        ZovyeException::throwWith($e->getMessage(), $e->getCode(), $device);
    }

    $device->appShowMessage('领取成功，欢迎下次使用！');

} catch (ZovyeException $e) {
    $log['error'] = $e->getMessage();

    if (isset($account) && $account->isThirdPartyPlatform() && isset($user)) {
        Account::updateQueryLogCBData($account, $user, $device, [
            'data' => [
                'error' => $e->getMessage(),
            ],
        ]);
    }

    $device = $e->getDevice();
    if ($device) {
        $device->appShowMessage($e->getMessage(), 'error');
    }

} catch (Exception $e) {

    $log['error'] = $e->getMessage();

} finally {

    $log['serial'] = Request::str('serial');

    if ($log['error']) {
        Log::error('create_order_account', $log);
    } else {
        Log::debug('create_order_account', $log);
    }
}

