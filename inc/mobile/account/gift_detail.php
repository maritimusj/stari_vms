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

$device = Device::get(request::str('device'), true);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

app()->giftDetailPage([
    'user' => $user,
    'device' => $device,
]);