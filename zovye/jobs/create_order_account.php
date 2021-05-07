<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\createOrderAccount;

use Exception;
use RuntimeException;
use zovye\Account;
use zovye\CtrlServ;
use zovye\Device;
use zovye\model\deviceModelObj;
use zovye\request;
use zovye\Order;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\ZovyeException;
use function zovye\is_error;

$account_id = request::str('account');
$device_id = request::str('device');
$user_id = request::str('user');
$goods_id = request::str('goods');
$order_uid = request::str('orderUID');
$ip = request::str('ip');

$params = [
    'account' => $account_id,
    'device' => $device_id,
    'user' => $user_id,
    'goods' => $goods_id,
    'orderUID' => $order_uid,
    'ip' => $ip,
];

$op = request::op('default');

if ($op == 'create_order_account' && CtrlServ::checkJobSign($params)) {
    try {
        /** @var deviceModelObj $device */
        $device = Device::get($device_id);
        if (empty($device)) {
            ZovyeException::throwWith('找不到指定的设备:' . $device_id, -1);
        }

        /** @var userModelObj $user */
        $user = User::get($user_id);
        if (empty($user) || $user->isBanned()) {
            ZovyeException::throwWith('找不到指定的用户或者已禁用!', -1, $device);
        }

        if (!$user->lock()) {
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

                $max_retries = intval(\zovye\settings('order.retry.max', 0));
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

        if (empty($order)) {
            //检查用户是否允许
            $res = Util::isAvailable($user, $account, $device);
            if (is_error($res)) {
                ZovyeException::throwWith($res['message'], $device);
            }
        }

        $goods = empty($goods_id) ? $device->getGoodsByLane(0) : $device->getGoods($goods_id);
        if (empty($goods)) {
            ZovyeException::throwWith('找不到商品！', -1, $device);
        }

        if ($goods['num'] < 1) {
            ZovyeException::throwWith('商品数量不足！', -1, $device);
        }

        $data = [
            'online' => false,
            'level' => empty($order) ? LOG_GOODS_CB : LOG_GOODS_RETRY,
            'goodsId' => $goods['id'],
            'ip' => empty($ip) ? $user->getLastActiveData('ip', '') : $ip,
            $device,
            $user,
            $account,
        ];

        if (!empty($order_uid)) {
            $data['orderId'] = $order_uid;
        }

        if (!empty($order)) {
            $data[] = $order;
        }

        try {

            $result = Util::openDevice($data);
            $params['result'] = $result;

        } catch (Exception $e) {
            ZovyeException::throwWith($e->getMessage(), $e->getCode(), $device);
        }

    } catch (ZovyeException $e) {
        $params['error'] = $e->getMessage();

        $device = $e->getDevice();
        if ($device) {
            $device->appShowMessage($e->getMessage(), 'error');
        }
    }
} else {
    $params['error'] = '参数签名检验失败！';
}

$params['serial'] = request::str('serial');

Util::logToFile('account', $params);
