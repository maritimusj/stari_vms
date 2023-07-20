<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'default') {
    if (Request::bool('charging')) {
        Response::alert('请关联充电桩小程序');
    }
    //检查设备
    $device_id = request('id'); //设备ＩＤ
    if (empty($device_id)) {
        Response::alert('请扫描设备二维码，谢谢！', 'error');
    }

    $device = Device::find($device_id, ['imei', 'shadow_id']);
    if (empty($device)) {
        Response::alert('设备二维码不正确！', 'error');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $device_id) {
        Response::alert('设备二维码不正确，请重新扫描！', 'error');
    }

    Response::redirect(Util::murl('entry', ['from' => 'device', 'device' => $device->getShadowId()]));

} elseif ($op == 'feed_back') {

    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    $device_imei = Request::str('device');
    $device = Device::get($device_imei, true);

    if (!$device) {
        JSON::fail('找不到这台设备！');
    }

    $text = Request::trim('text');
    $pics = Request::array('pics');

    if (empty($text)) {
        JSON::fail('请输入反馈内容！');
    }

    $data = [
        'device_id' => $device->getId(),
        'user_id' => $user->getId(),
        'text' => $text,
        'pics' => serialize($pics),
        'createtime' => time(),
    ];

    if (m('device_feedback')->create($data)) {
        JSON::success('反馈成功！');
    } else {
        JSON::fail('反馈失败！');
    }

} elseif ($op == 'detail') {

    $device = Device::get(Request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $detail = $device->getOnlineDetail();
    if ($detail && $detail['mcb'] && $detail['mcb']['online']) {
        $device->updateSettings('last.online', time());
    } else {
        $device->updateSettings('last.online', 0);
    }

    $device->save();

    JSON::success($detail);

} elseif ($op == 'is_ready') {

    $device = Device::get(Request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $is_ready = false;

    $scene = Request::str('scene');
    if ($scene == 'online') {
        $is_ready = $device->isMcbOnline(false);
    } elseif ($scene == 'lock') {
        if (!$device->isLocked()) {
            if (Locker::try("device:is_ready:{$device->getId()}")) {
                $is_ready = true;
            }
        }
    }

    $device->setReady($scene, $is_ready);

    JSON::success([
        'is_ready' => $is_ready,
    ]);

} elseif ($op == 'goods') {

    $device = Device::get(Request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    if (Request::has('user')) {
        $user = User::get(Request::str('user'), true);
    } else {
        $user = Session::getCurrentUser();
    }

    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    $type = Request::str('type'); //free or pay or balance

    if ($type == 'exchange') {
        $result = $device->getGoodsList($user, [Goods::AllowBalance]);
    } elseif ($type == 'free') {
        $result = $device->getGoodsList($user, [Goods::AllowFree]);
    } elseif ($type == 'pay') {
        $result = $device->getGoodsAndPackages($user, [Goods::AllowPay]);
    } else {
        $result = [];
    }

    JSON::success($result);

} elseif ($op == 'choose_goods') {
    $device = Device::get(Request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    $id = Request::int('goods');
    $num = Request::int('num', 1);

    $goods = $device->getGoods($id);
    if (empty($goods)) {
        JSON::fail('商品不存在！');
    }

    if ($goods['num'] < $num) {
        JSON::fail('商品库存不足！');
    }

    if (!$goods[Goods::AllowFree]) {
        JSON::fail('商品不允许免费领取！');
    }

    $user->setLastActiveData('goods', $goods['id']);
    JSON::success('已保存用户选择！');

} elseif ($op == 'get') {

    if (App::isCZTVEnabled()) {
        $user = User::get(Request::str('user'), true);
        if (empty($user) || $user->isBanned()) {
            JSON::fail('找不到用户！');
        }

        $device = $user->getLastActiveDevice();
        if (empty($device)) {
            JSON::fail('请重新扫描设备二维码！');
        }

        $result = CZTV::get($user, $device->getUid(), Request::int('goods'));
        JSON::result($result);
    }
} elseif ($op == 'schedule') {

    $device = Device::get(Request::json('device', 0));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $serial = Request::json('serial', '');
    if (empty($serial) || $device->settings('schedule.serial', '') !== $serial) {
        Response::echo('abort');
    }

    $user = User::getPseudoUser();

    try {
        $goods = $device->getGoodsByLane(0);
        if (empty($goods)) {
            throw new RuntimeException('找不到可用商品！');
        }

        if ($goods['num'] < 1) {
            throw new RuntimeException('对不起，商品数量不足！');
        }

        if (empty($order_no)) {
            $order_no = Order::makeUID($user, $device, sha1(REQUEST_ID));
        }

        $account = Account::getPseudoAccount();
        if (empty($account)) {
            throw new RuntimeException('找不到可用的公众号！');
        }

        if (!Job::createAccountOrder([
            'account' => $account->getId(),
            'device' => $device->getId(),
            'user' => $user->getId(),
            'goods' => $goods['id'],
            'orderUID' => $order_no,
        ])) {
            throw new RuntimeException('创建任务失败！');
        }

        Response::echo('Ok');

    } catch (RuntimeException $e) {
        Log::error('device_schedule', [
            'request' => Request::json(),
            'error' => $e->getMessage(),
        ]);
    }

}