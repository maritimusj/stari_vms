<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use Exception;
use RuntimeException;

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail(['text' => '领取失败', 'msg' => '找不到用户或者用户无法领取']);
}

if (!$user->acquireLocker('get::x')) {
    JSON::fail(['text' => '领取失败', 'msg' => '用户锁定失败，请重试']);
}

$ticket = request::str('ticket');
if (empty($ticket)) {
    JSON::fail(['text' => '领取失败', 'msg' => '请重新扫描设备二维码 [601]']);
}

try {
    $ticket_data_saved = $user->getLastActiveData('ticket', []);
    if (empty($ticket_data_saved) ||
        $ticket !== $ticket_data_saved['id'] ||
        time() - $ticket_data_saved['time'] > settings('user.scanAlive', VISIT_DATA_TIMEOUT)) {
        throw new RuntimeException('请重新扫描设备二维码 [601]');
    }

    $account = Account::get($ticket_data_saved['accountId']);
    if (empty($account)) {
        throw new RuntimeException('请重新扫描设备二维码 [602]');
    }

    $device = Device::get($ticket_data_saved['deviceId']);
    if (empty($device)) {
        throw new RuntimeException('请重新扫描设备二维码 [603]');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $ticket_data_saved['shadowId']) {
        throw new RuntimeException('请重新扫描设备二维码 [604]！');
    }

    if (!$device->isMcbOnline()) {
        throw new RuntimeException('设备不在线！');
    }

    //检查用户定位
    if (Util::mustValidateLocation($user, $device)) {
        throw new RuntimeException('定位超时，请重新扫描设备二维码 [605]');
    }

    $goods_id = request::int('goodsid');
    if (empty($goods_id)) {
        throw new RuntimeException('请指定要出货的商品 [605]');
    }

    $result = Util::transactionDo(function () use ($device, $user, $account, $goods_id, $ticket_data_saved) {
        //出货流程，EventBus会抛出异常
        $result = Util::openDevice([$device, $user, $account,
            'level' => LOG_GOODS_GET,
            'goodsId' => $goods_id,
            'online' => false,
        ]);
        if (is_error($result)) {
            if ($result['errno'] === State::ERROR_LOCK_FAILED && settings('order.waitQueue.enabled', false)) {
                $params = [
                    'account' => $account->getId(),
                    'device' => $device->getId(),
                    'user' => $user->getId(),
                    'goods' => $goods_id,
                    'ip' => $user->getLastActiveData('ip', CLIENT_IP),
                ];
                if (!Job::createAccountOrder($params)) {
                    throw new RuntimeException('启动排队任务失败！');
                }
                return ['message' => '正在排队出货，请稍等！'];
            }

            return $result;
        }

        $order = Order::get($result['orderId']);
        if ($order) {
            $order->setExtraData('ticket', $ticket_data_saved);
            if (!$order->save()) {
                throw new RuntimeException('保存订单数据失败！');
            }

            if ($ticket_data_saved['questionnaireAccountId']) {
                $questionnaire = Account::get($ticket_data_saved['questionnaireAccountId']);
            } elseif ($account->isQuestionnaire()) {
                $questionnaire = $account;
            }

            if ($questionnaire) {
                $log = $questionnaire->logQuery(['level' => $questionnaire->getId(), 'title' => $ticket_data_saved['id']])->findOne();
                if ($log) {
                    $log->setData('order', $order->profile());
                    if (!$log->save()) {
                        throw new RuntimeException('保存订单数据失败！');
                    }
                }
            }

            return [
                'message' => '领取成功，欢迎下次使用！',
                'orderId' => $order->getId(),
            ];
        }

        return err('创建订单失败！');
    });

    if (is_error($result)) {
        $device->appShowMessage('出货失败，请稍后再试！', 'error');
        throw new RuntimeException($result['message']);
    }

    if ($result['message']) {
        $device->appShowMessage($result['message']);
    }
    
    $response = [
        'ok' => empty($result['orderId']) ? 0 : 1,
        'text' => $result['title'],
        'msg' => $result['msg'],
    ];

    //成功转跳
    $url = $device->getRedirectUrl()['url'];
    if (!empty($url)) {
        $response['url'] = $url;
    }

    JSON::success($response);

} catch (Exception $e) {

    $user->setLastActiveData('ticket', []);

    $response = ['text' => '领取失败', 'msg' => $e->getMessage()];
    if (isset($device)) {
        //失败转跳
        $url = $device->getRedirectUrl('fail')['url'];
        if (!empty($url)) {
            $response['url'] = $url;
        }
    }

    JSON::fail($response);
}
