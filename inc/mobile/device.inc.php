<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;
use zovye\domain\Account;
use zovye\domain\Cron;
use zovye\domain\Device;
use zovye\domain\DeviceFeedback;
use zovye\domain\Goods;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'default') {
    if (Request::bool('charging')) {
        Response::alert('请关联充电桩小程序！');
    }
    //检查设备
    $device_id = Request::trim('id'); //设备ＩＤ
    if (empty($device_id)) {
        Response::alert('请扫描设备二维码，谢谢！', 'error');
    }

    $device = Device::find($device_id, ['imei', 'shadow_id']);
    if (empty($device)) {
        Response::alert('设备二维码不正确！', 'error');
    }

    //开启了shadowId的设备，只能通过shadowId找到
    if ($device->isActiveQRCodeEnabled() && $device->getShadowId() !== $device_id) {
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

    if (DeviceFeedback::create($data)) {
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
        if (Locker::try("device:is_ready:{$device->getId()}")) {
            $is_ready = true;
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

} elseif ($op == 'schedule') {

    Log::debug('schedule', [
        'cron' => Request::bool('cron'),
        'data' => Request::raw(),
    ]);

    if (!App::isDeviceScheduleTaskEnabled()) {
        Response::echo(CtrlServ::ABORT);
    }

    $device_id = Request::json('device', 0);

    $device = Device::get($device_id);
    if (empty($device)) {
        Log::error('schedule', '找不到这个设备：'.$device_id);
        Response::echo(CtrlServ::ABORT);
    }

    $cron_id = Request::json('cron', 0);
    $cron = Cron::query(['id' => $cron_id])->findOne();
    if (empty($cron)) {
        Log::error('schedule', '找不到这个任务：'.$cron_id);
        Response::echo(CtrlServ::ABORT);
    }

    if (sha1(App::uid().$cron->getUid()) !== Request::json('sign')) {
        Log::error('schedule', [
            'data' => Request::raw(),
            'error' => '签名不正确！',
        ]);
        Response::echo(CtrlServ::ABORT);
    }

    $cron->setTotal($cron->getTotal() + 1);
    $cron->save();

    $user = User::getPseudoUser();

    try {
        $goods = $device->getGoodsByLane(0);
        if (empty($goods)) {
            throw new RuntimeException('找不到可用商品！');
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
            'ignoreGoodsNum' => 1,
        ])) {
            throw new RuntimeException('创建任务失败！');
        }

    } catch (RuntimeException $e) {
        Log::error('schedule', [
            'request' => Request::json(),
            'error' => $e->getMessage(),
        ]);
    }

    Response::echo(CtrlServ::OK);

} elseif ($op == 'lane') {
    $device_id = Request::int('id');

    $device = Device::get($device_id);
    if (empty($device)) {
        JSON::fail('对不起，请重新扫描设备二维码！');
    }

    if ($device->isMaintenance()) {
        JSON::fail('对不起，设备正在维护中！');
    }

    $lane_id = Request::int('lane');
    $goods = $device->getGoodsByLane($lane_id, ['fullPath', 'useImageProxy']);
    if (empty($goods)) {
        JSON::fail('对不起，没有商品信息！');
    }

    if ($goods['num'] < 1) {
        JSON::fail('对不起，商品已售罄！');
    }

    $data = $device->profile();

    $data['goods'] = $goods;

    JSON::success($data);
} 

JSON::fail('不正确的请求！');