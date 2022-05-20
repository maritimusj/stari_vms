<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$op = request::op('default');
$from = request::str('from');
$device_id = request::str('device');

//获取支付宝用户信息
if ($op == 'auth') {
    $code = request::str('auth_code');
    if (empty($code)) {
        Util::resultAlert('获取用户auth_code失败！', 'error');
    }
    if (!Util::getAliUser($code)) {
        Util::resultAlert('获取用户信息失败[02]', 'error');
    }
}

if (!App::isAliUser()) {
    $retries = request::int('retries');
    if ($retries > 3) {
        Util::resultAlert('获取用户信息失败[01]', 'error');
    }
    $cb_url = Util::murl('ali', [
        'op' => 'auth',
        'from' => $from,
        'device' => $device_id,
        'retries' => $retries + 1,
    ]);

    app()->aliAuthPage($cb_url);
}

//用户扫描设备，进入设备页面

$device = Device::find($device_id, ['imei', 'shadow_id']);
if (empty($device)) {
    Util::resultAlert('请重新扫描设备上的二维码！', 'error');
}

if ($device->isDown()) {
    Util::resultAlert('设备维护中，请稍后再试！', 'error');
}

$user = Util::getCurrentUser();
if (empty($user)) {
    Util::resultAlert('请重新扫描二维码，谢谢！', 'error');
}

if ($user->isBanned()) {
    Util::resultAlert('用户帐户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled()) {
    if (!$user->isIDCardVerified()) {
        app()->showTemplate(Theme::file('verify_18'), [
            'verify18' => settings('user.verify_18', []),
            'entry_url' => Util::murl('ali', ['from' => $from, 'device' => $device_id]),
        ]);
    }
}

//开启了shadowId的设备，只能通过shadowId找到
if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $device_id) {
    Util::resultAlert('设备二维码不匹配！', 'error');
}

if ($from == 'device') {
    if ($device->isReadyTimeout()) {
        //设备准备页面，检测设备是否在线等等
        $tpl_data = Util::getTplData([$device, $user]);
        app()->devicePreparePage($tpl_data);
    }
    $user->cleanLastActiveData();
}

//设置用户最后活动数据
$user->setLastActiveDevice($device);

$tpl_data = Util::getTplData([$user, $device]);
$tpl_data['from'] = $from;
//设备首页
app()->devicePage($tpl_data);