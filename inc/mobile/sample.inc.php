<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

if (!App::isFlashEggEnabled()) {
    Response::alert('该功能没有启用，请联系管理员，谢谢！', 'error');
}

$op = Request::op('default');

//获取关注公众号二维码
if ($op == 'qrcode') {
    $url = Wx::getTempQRCodeUrl(http_build_query([
        'app' => app::uid(),
        'device' => Request::trim('device'),
    ]));
    if ($url) {
        JSON::success(['url' => $url]);
    }
    JSON::fail('无法获取公众号二维码！');
}

$params = [
    'create' => true,
    'update' => true,
];

$getUserFN = function () use (&$params) {
    $user = Session::getCurrentUser($params);
    if (empty($user)) {
        Response::alert('请用微信或者支付宝扫描二维码，谢谢！', 'error');
    }

    if ($user->isBanned()) {
        Response::alert('用户暂时无法使用该功能，请联系管理员！', 'error');
    }

    return $user;
};

$device_uid = Request::str('device');
if ($device_uid) {
    $device = Device::get($device_uid, true);
}

if (empty($device)) {
    /** @var userModelObj $user */
    $user = $getUserFN();
    $device = $user->getLastActiveDevice();
}

if (empty($device)) {
    Response::alert('请重新扫描设备二维码！', 'error');
}

$params['from'] = [
    'src' => 'device',
    'device' => [
        'name' => $device->getName(),
        'imei' => $device->getImei(),
    ],
    'ip' => CLIENT_IP,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
];

/** @var userModelObj $user */
$user = $getUserFN();

//记录设备ID
$user->setLastActiveDevice($device);

if ($op == 'check') {
    JSON::success([
        'user' => $user->profile(false),
        'subscribed' => User::isSubscribed($user),
    ]);
}

if (!User::isSubscribed($user)) {
    Response::followPage([
        'user' => $user,
        'device' => $device,
    ]);
}

//默认显示商品列表
if ($op == 'default') {
    Response::giftGoodsListPage([
        'user' => $user,
        'device' => $device,
    ]);
}

//获取商品列表
if ($op == 'goods') {
    $payload = $device->getPayload(true);
    $result = $payload['cargo_lanes'] ?? [];

    $allow_free = Request::bool('free');
    $allow_pay = Request::bool('pay');

    $isLimitedFN = function ($goods_id) use ($user, $device) {
        $goods = Goods::get($goods_id);
        if (empty($goods)) {
            return true;
        }

        if ($goods->getType() != Goods::FlashEgg) {
            return true;
        }

        $account = $goods->getAccount();
        if (empty($account) || $account->isBanned()) {
            return true;
        }

        $res = Helper::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);
        return is_error($res);
    };

    $goods = [];
    foreach ($result as $entry) {
        if ($allow_free && $entry[Goods::AllowFree] or $allow_pay && $entry[Goods::AllowPay] or !$allow_free && !$allow_pay) {
            $key = "goods{$entry['goods_id']}";
            if ($goods[$key]) {
                $goods[$key]['num'] += intval($entry['num']);
            } else {
                $goods[$key] = [
                    'id' => $entry['goods_id'],
                    'name' => $entry['goods_name'],
                    'price' => $entry['goods_price'],
                    'img' => Util::toMedia($entry['goods_img'], true),
                    'num' => intval($entry['num']),
                    'allow_free' => $entry[Goods::AllowFree],
                    'allow_pay' => $entry[Goods::AllowPay],
                    'limited' => $entry['num'] < 1 || $isLimitedFN($entry['goods_id']),
                ];
            }
        }
    }

    JSON::success(array_values($goods));
}

//获取商品和广告详情
if ($op == 'detail') {

    $goods_id = Request::int('id');
    $goods = Goods::get($goods_id);
    if (empty($goods) || $goods->isDeleted()) {
        JSON::fail('找不到这个商品！');
    }

    $account = $goods->getAccount();
    if (empty($account) || $account->isBanned()) {
        JSON::fail('商品对应的广告不可用！');
    }

    $device = Device::get(Request::str('device'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    //触发广告设备播放指定广告
    $adDeviceUID = $device->getAdDeviceUID();
    if ($adDeviceUID) {
        $area = $account->getArea();
        if ($area) {
            $flashEgg = new FlashEgg();
            if (DEBUG) {
                $flashEgg->debug();
            }
            $res = $flashEgg->triggerAdPlay($adDeviceUID, $area);
            if (is_error($res)) {
                Log::error('flash_egg', [
                    'device' => $device->getImei(),
                    'adDeviceUID' => $adDeviceUID,
                    'area' => $area,
                    'error' => $res['message'],
                ]);
            }
        }
    }

    JSON::success([
        'uid' => $account->getUid(),
        'media' => $account->getMedia(true),
        'duration' => $account->getDuration(),
    ]);
}

