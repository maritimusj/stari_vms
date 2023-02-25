<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\account\MeiPaAccount;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'meipa_auth') {
    $user = Util::getCurrentUser();
    if (empty($user)) {
        Util::resultAlert('请用微信打开！', 'error');
    }

    $openid = request::str('meipaopenid');
    $user->updateSettings('customData.meipa', [
        'openid' => $openid,
        'createdAt' => time(),
    ]);

    $device_uid = request::str('device');

    $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
    Util::redirect($url);
}

if (request::is_get()) {
    Util::resultAlert('出货成功，如果未领取到商品，请扫描二维码重试！');
}

MeiPaAccount::cb([
    'time' => request::str('time'),
    'apiid' => request::str('apiid'),
    'openid' => request::str('openid'),
    'carry_data' => request::str('carry_data'),
    'subscribe' => request::str('subscribe'),
    'order_sn' => request::str('order_sn'),
    'sing' => request::str('sing'),
]);

exit(REQUEST_ID);