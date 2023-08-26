<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use Exception;
use RuntimeException;

$app_id = Request::header('HTTP_X_APP_ID');
$ts = Request::header('HTTP_X_TIMESTAMP');
$token = Request::header('HTTP_X_TOKEN');

$data = Request::json();

Log::debug('tk', [
    'header' => [
        'app_id' => $app_id,
        'timestamp' => $ts,
        'token' => $token,
    ],
    'data' => $data,
]);

try {
    if (!App::isTKPromotingEnabled()) {
        throw new RuntimeException('没有启用这个功能！');
    }

    $config = Config::tk('config', []);
    if (empty($config) || empty($config['id']) || empty($config['secret'])) {
        throw new RuntimeException('配置不正确！');
    }

    if ($app_id !== $config['id'] || (new TKPromoting($config['id'], $config['secret']))->sign($ts) !== $token) {
        throw new RuntimeException('签名检验失败！');
    }

    $kind = $data['kind'];
    if (empty($kind) || $kind !== 'contract_policy') {
        throw new RuntimeException('无效的请求!');
    }

    $order_no = strval($data['order_no']);
    $user_uid = trim($data['extra'], TKPromoting::getUserPrefix());

    if (empty($order_no) || empty($user_uid)) {
        throw new RuntimeException('缺少必要信息！');
    }

    $user = User::get($user_uid, true);

    if (empty($user)) {
        throw new RuntimeException('找不到这个用户！');
    }

    if ($user->isBanned()) {
        throw new RuntimeException('用户不可用！');
    }

    $account = TKPromoting::getAccount();
    if (is_error($account)) {
        throw new RuntimeException($account['message']);
    }

    $device_uid = $data['device_no'];
    if (empty($device_uid)) {
        $device = $user->getLastActiveDevice();
    } else {
        $device = Device::get($device_uid, true);
    }

    if (empty($device)) {
        throw new RuntimeException('找不到设备或者用户操作超时！');
    }   

    $res = Helper::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);
    if (is_error($res)) {
        throw new RuntimeException($res['message']);
    }

    $order_uid = Order::makeUID($user, $device, $order_no);

    $res = Job::createAccountOrder([
        'orderUID' => $order_uid,
        'account' => $account->getId(),
        'device' => $device->getId(),
        'user' => $user->getId(),
        'goods' => 0, // 由出货job决定商品
        'ip' => $user->getLastActiveIp(),
        'tk_order_no' => $order_no,
    ]);

    if (!$res) {
        throw new RuntimeException('无法创建出货任务！');
    }
} catch (Exception $e) {
    Log::error('tk', [
        'error' => $e->getMessage(),
        'data' => $data,
    ]);
}

echo TKPromoting::RESPONSE;