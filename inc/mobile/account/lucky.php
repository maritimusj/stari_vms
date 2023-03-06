<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (!App::isFlashEggEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

$code = Request::str('code');
if (empty($code)) {
    Util::resultAlert('请求的中奖数据校验失败！', 'error');
}

$str = base64_decode($code);
if (empty($code)) {
    Util::resultAlert('请求的中奖数据校验失败！', 'error');
}

list($id, $serial, $secret) = explode(':', $str);

if (empty($id) || empty($serial) || empty($secret) || hash_hmac('sha256', "$id.$serial", App::secret()) !== $secret) {
    Util::resultAlert('请求的中奖数据校验失败！', 'error');
}

$lucky = FlashEgg::getLucky($id);
if (empty($lucky)) {
    Util::resultAlert('对不起，找不到这个抽奖活动！', 'error');
}

if (!$lucky->isEnabled()) {
    Util::resultAlert('对不起，这个抽奖活动已停用！', 'error');
}

$fn = Request::trim('fn');
if (empty($fn)) {
    App()->luckyRegistryPage([
        'user' => $user,
        'lucky' => $lucky,
        'code' => $code,
    ]);
}

if ($fn == 'save') {
    if (!$user->acquireLocker('flash_lucky:reg')) {
        JSON::fail('用户正忙，请稍后再试！');
    }

    if (!Locker::try("lucky:$serial")) {
        JSON::fail('无法锁定奖品，请稍后再试！');
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

    $log = FlashEgg::createLuckyLog([
        'lucky_id' => $lucky->getId(),
        'user_id' => $user->getId(),
        'serial' => $serial,
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
