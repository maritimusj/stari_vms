<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\YouFenAccount;

$op = Request::op('default');

if ($op == 'yf_auth') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $openid = Request::str('YF-OPENID');
    $user->updateSettings('customData.yf', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = Request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Util::redirect($url);
} else {

    Log::debug('youfen', [
        'raw' => Request::raw(),
    ]);

    YouFenAccount::cb([
        'request_id' => Request::str('request_id'),
        'openid' => Request::str('openid'),
        'sub_time' => Request::int('sub_time'),
        'wx_appid' => Request::str('wx_appid'),
        'notify_data' => Request::str('notify_data', '', true),
        'sub_type' => Request::int('sub_type'),
    ]);

    exit('success');
}