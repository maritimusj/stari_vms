<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$op = request::op('default');

if ($op == 'yf_auth') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $openid = request::str('YF-OPENID');
    $user->updateSettings('customData.yf', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Util::redirect($url);
} else {

    Log::debug('youfen', [
        'raw' => request::raw(),
    ]);

    YouFenAccount::cb([
        'request_id' => request::str('request_id'),
        'openid' => request::str('openid'),
        'sub_time' => request::int('sub_time'),
        'wx_appid' => request::str('wx_appid'),
        'notify_data' => request::str('notify_data', '', true),
        'sub_type' => request::int('sub_type'),
    ]);

    exit('success');
}