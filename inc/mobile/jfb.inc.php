<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\JfbAccount;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'jfb_auth') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $openid = Request::str('zhunaOpenId');
    $user->updateSettings('customData.jfb', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = Request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Util::redirect($url);
} else {
    JfbAccount::cb([
        'openid' => Request::str('open_id'),
        'device' => Request::str('facility_id'),
        'op_type' => Request::int('op_type'),
        'ad_code_no' => Request::str('ad_code_no'),
    ]);

    echo JfbAccount::CB_RESPONSE;
}

