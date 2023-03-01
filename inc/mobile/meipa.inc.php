<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\MeiPaAccount;

$op = Request::op('default');

if ($op == 'meipa_auth') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $openid = Request::str('meipaopenid');
    $user->updateSettings('customData.meipa', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = Request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Util::redirect($url);
}

if (Request::is_get()) {
    Util::resultAlert('出货成功，如果未领取到商品，请扫描二维码重试！');
}

MeiPaAccount::cb([
    'time' => Request::str('time'),
    'apiid' => Request::str('apiid'),
    'openid' => Request::str('openid'),
    'carry_data' => Request::str('carry_data'),
    'subscribe' => Request::str('subscribe'),
    'order_sn' => Request::str('order_sn'),
    'sing' => Request::str('sing'),
]);

exit(REQUEST_ID);