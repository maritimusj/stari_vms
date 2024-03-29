<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\business\FlashEgg;
use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

if (!App::isFlashEggEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

$getDeviceFN = function () use($user) {
    if (Request::has('device')) {
        return Device::get(Request::str('device'), true);
    } else {
        return $user->getLastActiveDevice();
    }
};

$fn = Request::trim('fn');

if ($fn == 'data') {

    $gift = FlashEgg::selectGiftForUser($user, $getDeviceFN());
    if (empty($gift)) {
        JSON::fail('暂时没有活动可以参加！');
    }

    $detail = FlashEgg::getUserGiftDetail($user, $gift);
    JSON::success($detail);

} elseif ($fn == 'reg') {

    $gift = FlashEgg::selectGiftForUser($user, $getDeviceFN());

    if (empty($gift)) {
        Response::alert('找不到这个活动！', 'error');
    }

    $detail = FlashEgg::getUserGiftDetail($user, $gift);

    if (empty($detail['all_acquired'])) {
        Response::alert('对不起，没有达到活动要求，暂时不能领取！');
    }

    Response::giftRegistryPage([
        'user' => $user,
        'device' => $getDeviceFN(),
        'gift' => $detail,
    ]);

} elseif ($fn == 'save') {

    if (!$user->acquireLocker('flash_gift:reg')) {
        JSON::fail('用户正忙，请稍后再试！');
    }

    $gift = FlashEgg::selectGiftForUser($user, $getDeviceFN());
    if (empty($gift)) {
        JSON::fail('找不到这个活动！');
    }

    $detail = FlashEgg::getUserGiftDetail($user, $gift);

    if (empty($detail['all_acquired'])) {
        JSON::fail('对不起，没有达到活动要求，暂时不能领取！');
    }

    if ($detail['uid'] != Request::trim('uid')) {
        JSON::fail('领取失败，请联系管理员！');
    }

    $name = Request::trim('name');
    $phone_num = Request::trim('phoneNumber');
    $location = Request::trim('location');
    $address = Request::trim('address');

    if (empty($name)) {
        JSON::fail('收件人姓名不能为空！');
    }
    if (empty($phone_num)) {
        JSON::fail('收件人电话不能为空！');
    }
    if (empty($address)) {
        JSON::fail('收件人详细地址不能为空！');
    }

    $log = FlashEgg::createGiftLog([
        'gift_id' => $gift->getId(),
        'user_id' => $user->getId(),
        'name' => $name,
        'phone_num' => $phone_num,
        'location' => $location,
        'address' => $address,
        'status' => 0,
    ]);

    if (empty($log)) {
        JSON::fail('领取失败，请联系管理员！');
    }

    JSON::success(['msg' => '领取成功，请注意查收！']);
}

Response::giftDetailPage([
    'user' => $user,
    'device' =>  $getDeviceFN()
]);