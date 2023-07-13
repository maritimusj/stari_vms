<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

if (!App::isFlashEggEnabled()) {
    JSON::fail('这个功能没有启用！');
}

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    Response::data(err('找不到用户或者用户无法领取！'));
}

$code = Request::str('code');
if (empty($code)) {
    Response::data(err('请求的中奖数据校验失败！'));
}

$str = base64_decode($code);
if (empty($code)) {
    Response::data(err('请求的中奖数据校验失败！'));
}

list($id, $serial, $secret) = explode(':', $str);

if (empty($id) || empty($serial) || empty($secret) || hash_hmac('sha256', "$id.$serial", App::secret()) !== $secret) {
    Response::data(err('请求的中奖数据校验失败！'));
}

$lucky = FlashEgg::getLucky($id);
if (empty($lucky)) {
    Response::data(err('对不起，找不到这个抽奖活动！'));;
}

if (!$lucky->isEnabled()) {
    Response::data(err('对不起，这个抽奖活动已停用！'));
}

if (!Locker::try("lucky:$serial")) {
    Response::data(err('无法锁定奖品，请稍后再试！'));
}

if (FlashEgg::isLuckyLogExists($serial)) {
    Response::data(err('对不起，该奖品已经登记领取！'));
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