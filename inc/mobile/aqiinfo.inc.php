<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\AQIInfoAccount;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\domain\User;
use zovye\util\TemplateUtil;

//如果是阿奇返回，则重新引导用户到设备页面
if (Request::str('appResult') == 'nomore') {

    $extra = Request::str('extra');
    if (empty($extra)) {
        Response::alert('请重新扫描设备二维码，谢谢！', 'error');
    }

    list($shadow_id, $user_id) = explode(':', $extra);

    if (empty($shadow_id) || empty($user_id)) {
        Response::alert('请重新扫描设备二维码[01]，谢谢！', 'error');
    }

    $device = Device::findOne(['shadow_id' => $shadow_id]);
    if (empty($device)) {
        Response::alert('请重新扫描设备二维码[02]，谢谢！', 'error');
    }

    $user = Session::getCurrentUser();
    if (empty($user)) {
        Response::alert('请重新扫描设备二维码[03]，谢谢！', 'error');
    }

    $from_user = User::get($user_id, true);
    if (empty($from_user)) {
        Response::alert('请重新扫描设备二维码[04]，谢谢！', 'error');
    }

    if ($user->getId() != $from_user->getId()) {
        Response::alert('请重新扫描设备二维码[05]，谢谢！', 'error');
    }

    $tpl_data = TemplateUtil::getTplData([$user, $device]);
    $tpl_data['exclude'][] = AQIInfoAccount::getUID();

    //设备首页
    Response::devicePage($tpl_data);
}

$raw = Request::raw();
if (empty($raw)) {
    Response::alert('请重新扫描设备二维码，谢谢！');
}

parse_str($raw, $data);

Log::debug('aqiinfo', [
    'raw' => $raw,
    'data' => $data,
]);

if (App::isAQiinfoEnabled()) {
    AQIInfoAccount::cb(Account::AQIINFO, $data);
} else {
    Log::warning('aqiinfo', [
        'error' => '阿旗数据平台没有启用！',
    ]);
}

exit(AQIInfoAccount::CB_RESPONSE);