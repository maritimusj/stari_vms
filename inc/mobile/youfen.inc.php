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

    $openid = request::str('OPENID');
    $user->updateSettings('customData.yf.openid', $openid);

    $device_uid = request::str('device');

    Util::redirect(Util::murl('entry', ['device' => $device_uid, 'from' => 'device']));
} else {

    Log::debug('youfen', [
        'raw' => request::raw(),
    ]);

    YouFenAccount::cb(request::json());

    exit('success');
}