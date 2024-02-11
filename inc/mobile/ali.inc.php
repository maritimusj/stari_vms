<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Device;
use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');
$from = Request::str('from');
$device_id = Request::str('device');
$lane_id = Request::isset('lane') ? Request::int('lane') : null;

//获取支付宝用户信息
if ($op == 'auth') {
    $code = Request::str('auth_code');
    if (empty($code)) {
        Response::alert('获取用户auth_code失败！', 'error');
    }
    if (!Session::getAliUser($code)) {
        Response::alert('获取用户信息失败[02]', 'error');
    }
}

if (!Session::isAliUser()) {
    $retries = Request::int('retries');
    if ($retries > 3) {
        Response::alert('获取用户信息失败[01]', 'error');
    }

    $cb_url = Util::murl('ali', [
        'op' => 'auth',
        'from' => $from,
        'device' => $device_id,
        'lane' => $lane_id,
        'retries' => $retries + 1,
    ]);

    Response::aliAuthPage([
        'cb_url' => $cb_url,
    ]);
}

//用户扫描设备，进入设备页面
$device = Device::find($device_id, ['imei', 'shadow_id']);
if (empty($device)) {
    Response::alert('请重新扫描设备上的二维码！', 'error');
}

if ($device->isMaintenance()) {
    Response::alert('设备维护中，请稍后再试！', 'error');
}

$user = Session::getCurrentUser();
if (empty($user)) {
    Response::alert('请重新扫描二维码，谢谢！', 'error');
}

if ($user->isBanned()) {
    Response::alert('用户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled()) {
    if (!$user->isIDCardVerified()) {
        Response::showTemplate('verify_18', [
            'verify18' => settings('user.verify_18', []),
            'entry_url' => Util::murl('ali', ['from' => $from, 'device' => $device_id, 'lane'=> $lane_id]),
        ], true);
    }
}

//开启了shadowId的设备，只能通过shadowId找到
if ($device->isActiveQRCodeEnabled() && $device->getShadowId() !== $device_id) {
    Response::alert('设备二维码不匹配！', 'error');
}

$tpl_data = TemplateUtil::getTplData([$user, $device]);
$tpl_data['from'] = $from;
$tpl_data['lane_id'] = $lane_id;

if ($from == 'device') {
    if ($device->isReadyTimeout()) {
        //设备准备页面，检测设备是否在线等等
        Response::devicePreparePage($tpl_data);
    }
    $user->cleanLastActiveData();
}

//设置用户最后活动数据
$user->setLastActiveDevice($device);

//带货道参数的链接，直接进入商品购买页面
if (App::isDeviceLaneQRCodeEnabled() && isset($lane_id)) {
    Response::deviceLanePage($tpl_data);
}

//设备首页
Response::devicePage($tpl_data);