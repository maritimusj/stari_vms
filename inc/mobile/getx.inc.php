<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use Exception;
use RuntimeException;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\Goods;
use zovye\domain\Order;
use zovye\domain\Questionnaire;
use zovye\util\DeviceUtil;
use zovye\util\LocationUtil;

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail(['text' => '领取失败', 'msg' => '找不到用户或者用户无法领取']);
}

if (!$user->acquireLocker('get::x')) {
    JSON::fail(['text' => '领取失败', 'msg' => '用户锁定失败，请重试']);
}

$ticket = Request::str('ticket');
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
    if ($device->isActiveQRCodeEnabled() && $device->getShadowId() !== $ticket_data_saved['shadowId']) {
        throw new RuntimeException('请重新扫描设备二维码 [604]！');
    }

    if (!$device->isMcbOnline()) {
        throw new RuntimeException('设备不在线！');
    }

    //检查用户定位
    if (LocationUtil::mustValidate($user, $device)) {
        throw new RuntimeException('定位超时，请重新扫描设备二维码 [605]');
    }

    $goods_id = $ticket_data_saved['goodsId'];
    if (empty($goods_id)) {
        $goods_id = Request::int('goodsId');
    }

    if (empty($goods_id)) {
        throw new RuntimeException('请指定要出货的商品 [605]');
    }

    $goods = $device->getGoods($goods_id);
    if (empty($goods) || empty($goods[Goods::AllowFree])) {
        throw new RuntimeException('无法领取这个商品，请联系管理员！');
    }

    //出货流程，EventBus会抛出异常
    $result = DeviceUtil::open([
        $device,
        $user,
        $account,
        'level' => LOG_GOODS_FREE,
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
                'ip' => $user->getLastActiveIp(),
            ];

            if (!Job::createAccountOrder($params)) {
                throw new RuntimeException('启动排队任务失败！');
            }

            JSON::success(['message' => '正在排队出货，请稍等！']);
        }

        throw new RuntimeException($result['message']);
    }

    $order = Order::get($result['orderId']);
    if (empty($order)) {
        throw new RuntimeException('出货失败，找不到这个订单！');
    }

    if ($ticket_data_saved['questionnaireAccountId']) {
        $questionnaire = Account::get($ticket_data_saved['questionnaireAccountId']);
    } elseif ($account->isQuestionnaire()) {
        $questionnaire = $account;
    } else {
        $questionnaire = null;
    }

    if ($questionnaire) {
        $result = Questionnaire::submitAnswer(
            $questionnaire,
            $ticket_data_saved['answer'] ?? [],
            $user,
            $device
        );

        if (is_error($result)) {
            throw new RuntimeException('问卷答案没通过审核！');
        }

        $result->setData('order', $order->profile());
        if (!$result->save()) {
            throw new RuntimeException('保存订单数据失败！');
        }

        unset($ticket_data_saved['answer']);
        $ticket_data_saved['logId'] = $result->getId();
    }

    $order->setExtraData('ticket', $ticket_data_saved);
    if (!$order->save()) {
        throw new RuntimeException('保存订单数据失败！');
    }

    $response = [
        'ok' => true,
        'text' => '领取成功',
        'msg' => '谢谢，欢迎下次使用！',
    ];

    $device->appShowMessage($response['msg']);

    if (App::isFlashEggEnabled()) {
        $goods = Goods::get($goods_id);
        if ($goods && $goods->isFlashEgg()) {
            $url = $goods->getExtraData('redirect_url');
        }
    }

    if (empty($url)) {
        //成功转跳
        $url = $device->getRedirectUrl()['url'];
    }

    if (!empty($url)) {
        $response['url'] = $url;
    }

    JSON::success($response);

} catch (Exception $e) {

    Log::debug('get', [
        'ticket' => $ticket_data_saved ?? null,
        'error' => $e->getMessage(),
    ]);

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
