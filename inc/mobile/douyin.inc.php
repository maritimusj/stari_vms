<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op();

if ($op == 'auth' || $op == 'get_openid') {
    $code = request::str('code');
    if (empty($code)) {
        Util::resultAlert('获取用户授权code失败！', 'error');
    }

    $user = Util::getDouYinUser($code);
    if (empty($user)) {
        Util::resultAlert('获取用户信息失败[02]', 'error');
    }

    if (request::has('id')) {
        $account = Account::get(request::int('id'));
        if (empty($account)) {
            Util::resultAlert('找不到指定的吸粉广告！', 'error');
        }
        $account->updateSettings('config.openid', $user->getOpenid());
        Util::resultAlert('授权接入成功！');

    } elseif (request::has('uid')) {
        $account = Account::findOneFromUID(request::trim('uid'));
        if (empty($account)) {
            Util::resultAlert('找不到指定的吸粉广告！', 'error');
        }
        $account->updateSettings('config.openid', $user->getOpenid());
        Util::resultAlert('授权接入成功！');
    }

} elseif ($op == 'account') {
    $device = Device::findOne(['shadow_id' => request::str('device')]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }
    $user = User::get(request::trim('user'), true);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $res = Account::match($device, $user, ['type' => Account::DOUYIN]);
    $data = [];
    foreach ($res as $entry) {
        if ($entry['url'] && $entry['openid']) {
            $data[] = [
                'uid' => $entry['uid'],
                'name' => $entry['name'],
                'title' => $entry['title'],
                'descr' => $entry['descr'],
                'clr' => $entry['clr'],
                'img' => Util::toMedia($entry['img'], true),
            ];
        }
    }

    JSON::success($data);

} elseif ($op == 'detail') {
    $user = User::get(request::trim('user'), true);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $device = Device::findOne(['shadow_id' => request::str('device')]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $account = Account::findOneFromUID(request::trim('uid'));
    if (empty($account)) {
        JSON::fail('找不到这个抖音号[01]');
    }
    if (!$account->isDouyin()) {
        JSON::fail('找不到这个抖音号[02]');
    }

    $url = $account->getConfig('url', '');
    if (empty($url)) {
        JSON::fail('抖音号没有正确配置[03]');
    }

    if (!Util::checkAvailable($user, $account, $device)) {
        JSON::fail('暂时无法免费领取，请重试[01]');
    }

    if (!Job::douyinOrder($user, $device, $account->getUid())) {
        JSON::fail('暂时无法免费领取，请重试[02]');
    }

    JSON::success([
        'redirect' => DouYin::makeHomePageUrl($url),
    ]);
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
    Util::resultAlert('用户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled()) {
    if (!$user->isIDCardVerified()) {
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

app()->douyinPage($device, $user);