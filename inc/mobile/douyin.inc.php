<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\business\DouYin;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\User;
use zovye\util\Helper;
use zovye\util\LocationUtil;
use zovye\util\TemplateUtil;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$op = Request::op();

if ($op == 'auth' || $op == 'get_openid') {
    $code = Request::str('code');
    if (empty($code)) {
        Response::alert('获取用户授权code失败！', 'error');
    }

    $user = Session::getDouYinUser($code);
    if (empty($user)) {
        Response::alert('获取用户信息失败[02]', 'error');
    }

    if (Request::has('id')) {
        $account = Account::get(Request::int('id'));
        if (empty($account)) {
            Response::alert('找不到指定的吸粉广告！', 'error');
        }
        $account->updateSettings('config.openid', $user->getOpenid());
        Response::alert('授权接入成功！');

    } elseif (Request::has('uid')) {
        $account = Account::findOneFromUID(Request::trim('uid'));
        if (empty($account)) {
            Response::alert('找不到指定的吸粉广告！', 'error');
        }
        $account->updateSettings('config.openid', $user->getOpenid());
        Response::alert('授权接入成功！');
    }

} elseif ($op == 'account') {
    $device = Device::findOne(['shadow_id' => Request::str('device')]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }
    $user = User::get(Request::trim('user'), true);
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
    $user = User::get(Request::trim('user'), true);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }

    $device = Device::findOne(['shadow_id' => Request::str('device')]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $account = Account::findOneFromUID(Request::trim('uid'));
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

    if (!Helper::checkAvailable($user, $account, $device)) {
        JSON::fail('暂时无法免费领取，请重试[01]');
    }

    if (!Job::douyinOrder($user, $device, $account->getUid())) {
        JSON::fail('暂时无法免费领取，请重试[02]');
    }

    JSON::success([
        'redirect' => DouYin::makeHomePageUrl($url),
    ]);
}

$from = Request::str('from');
$device_id = Request::str('device');

if (!Session::isDouYinUser()) {
    $retries = Request::int('retries');
    if ($retries > 3) {
        Response::alert('获取用户信息失败[01]', 'error');
    }
    $cb_url = Util::murl('douyin', [
        'op' => 'auth',
        'from' => $from,
        'device' => $device_id,
        'retries' => $retries + 1,
    ]);
    DouYin::redirectToAuthorizeUrl($cb_url);
}

$user = Session::getCurrentUser();
if (empty($user)) {
    unset($_SESSION['douyin_user_id']);
    Response::alert('请重新扫描二维码，谢谢！', 'error');
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
    Response::alert('用户暂时无法使用该功能，请联系管理员！', 'error');
}

if (App::isUserVerify18Enabled()) {
    if (!$user->isIDCardVerified()) {
        Response::showTemplate('verify_18', [
            'verify18' => settings('user.verify_18', []),
            'entry_url' => Util::murl('douyin', ['from' => $from, 'device' => $device_id]),
        ], true);
    }
}

//用户扫描设备，进入设备页面
$device = Device::find($device_id, ['imei', 'shadow_id']);
if (empty($device)) {
    Response::alert('请重新扫描设备上的二维码！', 'error');
}

//开启了shadowId的设备，只能通过shadowId找到
if ($device->isActiveQrcodeEnabled() && $device->getShadowId() !== $device_id) {
    Response::alert('设备二维码不匹配！', 'error');
}

if ($device->isMaintenance()) {
    Response::alert('设备维护中，请稍后再试！', 'error');
}

//检查用户定位
if (LocationUtil::mustValidate($user, $device)) {
    $user->cleanLastActiveData();
    $tpl_data = TemplateUtil::getTplData(
        [
            $user,
            $device,
            [
                'page.title' => '查找设备',
                'redirect' => Util::murl('entry', ['from' => 'location', 'device' => $device->getShadowId()]),
            ],
        ]
    );

    //定位页面
    Response::locationPage($tpl_data);
}

if ($from == 'device') {
    if (time() - $device->settings('last.online', 0) > 60) {
        //设备准备页面，检测设备是否在线等等
        $tpl_data = TemplateUtil::getTplData([$device, $user]);
        Response::devicePreparePage($tpl_data);
    }
    $user->remove('last');
}

Response::douyinPage([
    'device' => $device,
    'user' => $user,
]);