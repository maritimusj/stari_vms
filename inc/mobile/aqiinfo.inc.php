<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//如果是阿奇返回，则重新引导用户到设备页面
if (request::str('appResult') == 'nomore') {

    $extra = request::str('extra');
    if (empty($extra)) {
        Util::resultAlert('请重新扫描设备二维码，谢谢！', 'error');
    }

    list($shadow_id, $user_id) = explode(':', $extra);

    if (empty($shadow_id) || empty($user_id)) {
        Util::resultAlert('请重新扫描设备二维码[01]，谢谢！', 'error');
    }

    $device = Device::findOne(['shadow_id' => $shadow_id]);
    if (empty($device)) {
        Util::resultAlert('请重新扫描设备二维码[02]，谢谢！', 'error');
    }

    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请重新扫描设备二维码[03]，谢谢！', 'error');
    }

    $from_user = User::get($user_id, true);
    if (empty($from_user)) {
        Util::resultAlert('请重新扫描设备二维码[04]，谢谢！', 'error');
    }

    if ($user->getId() != $from_user->getId()) {
        Util::resultAlert('请重新扫描设备二维码[05]，谢谢！', 'error');
    }

    $tpl_data = Util::getTplData([$user, $device]);
    $tpl_data['exclude'][] = AQiinfoAccount::getUid();

    //设备首页
    app()->devicePage($tpl_data);
}

$raw = request::raw();
if (empty($raw)) {
    Util::resultAlert('请重新扫描设备二维码，谢谢！');
}

parse_str($raw, $data);

Util::logToFile('aqiinfo', [
    'raw' => $raw,
    'data' => $data,
]);

if (App::isAQiinfoEnabled()) {
    AQiinfoAccount::cb($data);
} else {
    Util::logToFile('aqiinfo', [
        'error' => '阿旗数据平台没有启用！',
    ]);
}


exit(AQiinfoAccount::CB_RESPONSE);