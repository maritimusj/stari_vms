<?php
namespace zovye;

$op = request::op();

if ($op == 'auth' || $op == 'get_openid') {
    $code = request::str('code');
    if (empty($code)) {
        Util::resultAlert('获取用户授权code失败！', 'error');
    }

    $user = Util::getDouYinUser($code, $device);
    if (empty($user)) {
        Util::resultAlert('获取用户信息失败[02]', 'error');
    }

    if (request::has('account')) {
        $account = Account::get(request::int('account'));
        if (empty($account)) {
            Util::resultAlert('找不到指定的吸粉广告！', 'error');
        }
        $account->updateSettings('config.openid', $user->getOpenid());
        Util::resultAlert('授权接入成功！');
    }
}

$from = request::str('from');
$device_id = request::str('device');

if (!App::isDouYinUser()) {
    $retries = request::int('retries');
    if ($retries > 3) {
        Util::resultAlert('获取用户信息失败[01]', 'error');
    }
    $cb_url = Util::murl('douyin', [
        'op' => 'auth',
        'from' => $from,
        'device' => $device_id,
        'retries' => $retries + 1,
    ]);
    DouYin::redirectToAuthorizeUrl($cb_url);
}

$user = Util::getCurrentUser();
if (empty($user)) {
    unset($_SESSION['douyin_user_id']);
    Util::resultAlert('请重新扫描二维码，谢谢！', 'error');
}

if (DouYin::isTokenExpired($user)) {
    unset($_SESSION['douyin_user_id']);
    //重新获取用户access_token
    $cb_url = Util::murl('douyin', [
        'op' => 'auth',
        'from' => $from,
        'device' => $device_id,
    ]);
    DouYin::redirectToAuthorizeUrl($cb_url);
}

if ($user->isBanned()) {
    Util::resultAlert('用户帐户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled()) {
    if(!$user->isIDCardVerified()) {
        app()->showTemplate(Theme::file('verify_18'), [
            'verify18' => settings('user.verify_18', []),
            'entry_url' => Util::murl('douyin', ['from' => $from, 'device' => $device_id]),
        ]);
    }
}

//用户扫描设备，进入设备页面
$device = Device::find($device_id, ['imei', 'shadow_id']);
if (empty($device)) {
    Util::resultAlert('请重新扫描设备上的二维码！', 'error');
}

if ($device->isDown()) {
    Util::resultAlert('设备维护中，请稍后再试！', 'error');
}

//开启了shadowId的设备，只能通过shadowId找到
if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $device_id) {
    Util::resultAlert('设备二维码不匹配！', 'error');
}

if ($from == 'device') {
    if (time() - $device->settings('last.online', 0) > 60) {
        //设备准备页面，检测设备是否在线等等
        $tpl_data = Util::getTplData([$device, $user]);
        app()->devicePreparePage($tpl_data);
    }
    $user->remove('last');
}

$res = Account::match($device, $user, ['state' => Account::DOUYIN]);

foreach($res as $entry) {
    if ($entry['url']) {
        Job::douyinOrder($user, $device, $entry['uid']);
        Util::redirect($entry['url']);
        exit();
    }
}

Util::resultAlert('暂时无法领取，请使用微信或者支付宝扫描二维码！', 'error');