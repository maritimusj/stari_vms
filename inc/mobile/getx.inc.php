<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use Exception;

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
    $ticket_data_saved = $user->settings('last.ticket', []);
    if (empty($ticket_data_saved) ||
        $ticket !== $ticket_data_saved['id'] ||
        time() - $ticket_data_saved['time'] > settings('user.scanAlive', VISIT_DATA_TIMEOUT)) {
        throw new Exception('请重新扫描设备二维码 [601]');
    }

    $account = Account::get($ticket_data_saved['accountId']);
    if (empty($account)) {
        throw new Exception('请重新扫描设备二维码 [602]');
    }

    $device = Device::get($ticket_data_saved['deviceId']);
    if (empty($device)) {
        throw new Exception('请重新扫描设备二维码 [603]');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $ticket_data_saved['shadowId']) {
        throw new Exception('请重新扫描设备二维码 [604]！');
    }

    if (!$device->isMcbOnline()) {
        throw new Exception('设备不在线！');
    }

    //检查用户定位
    if (Util::mustValidateLocation($user, $device)) {
        throw new Exception('定位超时，请重新扫描设备二维码 [605]');
    }

    $goods_id = request::int('goodsid');
    if (empty($goods_id)) {
        throw new Exception('请指定要出货的商品 [605]');
    }

    //出货流程，EventBus会抛出异常
    $result = Util::openDevice(['level' => LOG_GOODS_GETX, $device, $user, $account, 'goodsId' => $goods_id, 'online' => false]);
    if (is_error($result)) {
        $response = [
            'text' => $result['errno'] > 0 ? '请重试' : '领取失败',
            'msg' => $result['message'],
        ];

        $device->appShowMessage('出货失败，请稍后再试！', 'error');

        //失败转跳
        $url = $device->getRedirectUrl('fail')['url'];
        if (!empty($url)) {
            $response['url'] = $url;
        }

        JSON::fail($response);
    }

    $device->appShowMessage('领取成功，欢迎下次使用！');

    $response = [
        'ok' => $result['orderid'] ? 1 : 0,
        'text' => $result['title'],
        'msg' => $result['msg'],
    ];

    //返回用户当前余额
    if ($result['change']) {
        $response['balance'] = $user->getBalance()->total();
        $response['change'] = $result['change'];
    }

    //成功转跳
    $url = $device->getRedirectUrl()['url'];
    if (!empty($url)) {
        $response['url'] = $url;
    }

    JSON::success($response);

} catch (Exception $e) {

    $user->updateSettings('last.ticket', []);

    $response = ['text' => '领取失败', 'msg' => $e->getMessage()];
    if (isset($device)) {

        $device->appShowMessage('出货失败，请稍后再试！', 'error');

        //失败转跳
        $url = $device->getRedirectUrl('fail')['url'];
        if (!empty($url)) {
            $response['url'] = $url;
        }
    }

    JSON::fail($response);
}
