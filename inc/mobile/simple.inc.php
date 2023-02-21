<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\accountModelObj;
use zovye\model\userModelObj;

defined('IN_IA') or exit('Access Denied');

if (!App::isFlashEggEnabled()) {
    Util::resultAlert('该功能没有启用，请联系管理员，谢谢！', 'error');
}

$op = request::op('default');

//获取关注公众号二维码
if ($op == 'qrcode') {
    $res = Wx::getTempQRCodeTicket();
    if ($res && $res['ticket']) {
        JSON::success([
            'url' => 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$res['ticket'],
        ]);
    }
    JSON::fail('无法获取公众号二维码！');
}

$params = [
    'create' => true,
    'update' => true,
];

$getUserFN = function () use (&$params) {
    $user = Util::getCurrentUser($params);
    if (empty($user)) {
        Util::resultAlert('请用微信或者支付宝扫描二维码，谢谢！', 'error');
    }

    if ($user->isBanned()) {
        Util::resultAlert('用户暂时无法使用该功能，请联系管理员！', 'error');
    }

    return $user;
};

$device_uid = request::str('device');
if ($device_uid) {
    $device = Device::get($device_uid, true);
}

if (empty($device)) {
    /** @var userModelObj $user */
    $user = $getUserFN();
    $device = $user->getLastActiveDevice();
}

if (empty($device)) {
    Util::resultAlert('请重新扫描设备二维码！', 'error');
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

if (!$GLOBALS['_W']['fans']['follow']) {
    app()->followPage($user, $device);
}

//获取商品列表
if ($op == 'goods') {
    $payload = $device->getPayload(true);
    $result = $payload['cargo_lanes'] ?? [];

    $allow_free = request::bool('free');
    $allow_pay = request::bool('pay');

    $isLimitedFN = function ($goods_id) use($user) {
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

        $res = Util::checkAccountLimits($user, $account);
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
                    'limited' => $isLimitedFN($entry['goods_id']),
                ];
            }
        }
    }

    JSON::success(array_values($goods));
}

if ($op == 'detail') {

    $goods_id = request::int('id');
    $goods = Goods::get($goods_id);
    if (empty($goods) || $goods->isDeleted()) {
        JSON::fail('找不到这个商品！');
    }

    $account = $goods->getAccount();
    if (empty($account) || $account->isBanned()) {
        JSON::fail('商品暂时无法使用！');
    }

    $device = Device::get(request::str('device'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $res = Util::checkAvailable($user, $account, $device, ['ignore_assigned' => true]);
    if (is_error($res)) {
        JSON::fail($res);
    }

    JSON::success([
        'uid' => $account->getUid(),
        'media' => $account->getMedia(),
        'redirect' => Util::murl('account', [
            'op' => 'play',
            'uid' => $account->getUid(),
            'device' => $device->getUid(),
            'seconds' => 1,
        ]),
    ]);
}

app()->goodsListPage($user, $device);

