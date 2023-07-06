<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;

$data = Request::json();
$auth_key = Request::header('AuthKey');

Log::debug('tk', [
    'header' => $auth_key,
    '$data' => $data,
]);

try {
    if (!App::isTKPromotingEnabled()) {
        throw new RuntimeException('没有启用这个功能！');
    }

    $requestId = $data['requestId'];
    if (empty($requestId)) {
        throw new RuntimeException('无效的请求!');
    }

    $requestData = TKPromoting::decrypt($data['requestData']);
    if (empty($requestData)) {
        throw new RuntimeException('无法解密requestData');
    }

    if (TKPromoting::sign($requestData['eventTime']) !== $auth_key) {
        throw new RuntimeException('签名检验失败！');
    }

    $user_uid = $requestData['extra'];
    $proposalNo = $requestData['proposalNo'];

    if (empty($user_uid) || empty($proposalNo)) {
        throw new RuntimeException('缺少必要信息！');
    }

    $user = User::get($user_uid, true);

    if (empty($user)) {
        throw new RuntimeException('找不到这个用户！');
    }

    if ($user->isBanned()) {
        throw new RuntimeException('用户不可用！');
    }

    $user->updateSettings('tk.proposal', [
        'time' => time(),
        'No' => $proposalNo,
    ]);

    $account = TKPromoting::getAccount();
    if (is_error($account)) {
        throw new RuntimeException($account['message']);
    }

    $device = $user->getLastActiveDevice();
    if (empty($device)) {
        throw new RuntimeException('找不到设备或者用户操作超时！');
    }

    $res = Util::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);
    if (is_error($res)) {
        throw new RuntimeException($res['message']);
    }

    $order_uid = Order::makeUID($user, $device, $requestId);

    $res = Job::createAccountOrder([
        'orderUID' => $order_uid,
        'account' => $account->getId(),
        'device' => $device->getId(),
        'user' => $user->getId(),
        'goods' => 0, // 由出货job决定商品
        'ip' => $user->getLastActiveIp(),
        'proposalNo' => $proposalNo,
    ]);

    if (!$res) {
        throw new RuntimeException('无法启用出货任务！');
    }

    JSON::fail([
        'requestId' => $requestId,
        'responseTime' => date('YmdHis'),
        'responseCode' => '000_000_000',
        'responseMsg' => '成功',
        'responseData' => null,
    ]);

} catch (Exception $e) {

    Log::error('tk', [
        'header' => $auth_key,
        'data' => $data,
        'error' => $e->getMessage(),
    ]);

    JSON::fail([
        'requestId' => $requestId ?? '',
        'responseTime' => date('YmdHis'),
        'responseCode' => '000_000_001',
        'responseMsg' => $e->getMessage(),
        'responseData' => null,
    ]);
}