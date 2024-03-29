<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\JfbAccount;
use zovye\util\Util;

$op = Request::op('default');

if ($op == 'jfb_auth') {
    $user = Session::getCurrentUser();
    if (empty($user)) {
        Response::alert('请用微信打开！', 'error');
    }

    $openid = Request::str('zhunaOpenId');
    $user->updateSettings('customData.jfb', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = Request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Response::redirect($url);
} else {
    JfbAccount::cb([
        'openid' => Request::str('open_id'),
        'device' => Request::str('facility_id'),
        'op_type' => Request::int('op_type'),
        'ad_code_no' => Request::str('ad_code_no'),
    ]);

    echo JfbAccount::CB_RESPONSE;
}

