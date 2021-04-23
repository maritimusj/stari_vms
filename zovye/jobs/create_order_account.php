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
        /** @var userModelObj $user */
        $user = User::get($user_id);
        if (empty($user) || $user->isBanned()) {
            throw new RuntimeException('找不到指定的用户或者已禁用!');
        }

        if (!$user->lock()) {
            throw new RuntimeException('用户锁定失败!');
        }

        $account = Account::get($account_id);
        if (empty($account)) {
            throw new RuntimeException('找不到指定公众号！');
        }

        /** @var deviceModelObj $device */
        $device = Device::get($device_id);
        if (empty($device)) {
            throw new RuntimeException('找不到指定的设备:' . $device_id);
        }

        if (!empty($order_uid)) {
            $order = Order::get($order_uid, true);
            if ($order && $order->getResultCode() == 0) {
                throw new RuntimeException('订单已经存在！');
            }
        }

        if (empty($order)) {
            //检查用户是否允许
            $res = Util::isAvailable($user, $account, $device);
            if (is_error($res)) {
                throw new RuntimeException('无法领取:' . $res['message']);
            }
        }

        $goods = empty($goods_id) ? $device->getGoodsByLane(0) : $device->getGoods($goods_id);
        if (empty($goods)) {
            throw new RuntimeException('找不到商品！');
        }

        if ($goods['num'] < 1) {
            throw new RuntimeException('商品数量不足！');
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

        $result = Util::openDevice($data);

        $params['result'] = $result;

    } catch (Exception $e) {
        $params['error'] = $e->getMessage();
    }
} else {
    $params['error'] = '参数签名检验失败！';
}

$params['serial'] = request::str('serial');

Util::logToFile('account', $params);
