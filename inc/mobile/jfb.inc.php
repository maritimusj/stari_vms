<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'jfb_auth') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $openid = request::str('zhunaOpenId');
    $user->updateSettings('customData.jfb', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Util::redirect($url);
} else {
    JfbAccount::cb([
        'openid' => request::str('open_id'),
        'device' => request::str('facility_id'),
        'op_type' => request::int('op_type'),
        'ad_code_no' => request::str('ad_code_no'),
    ]);

    echo JfbAccount::CB_RESPONSE;
}

