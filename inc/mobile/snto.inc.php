<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\SNTOAccount;

if (App::isSNTOEnabled()) {

    $op = Request::op('default');
    if ($op == 'snto_auth') {
        $user = Session::getCurrentUser();
        if (empty($user)) {
            Response::alert('请用微信打开！', 'error');
        }

        $openid = Request::str('stOpenId');
        $user->updateSettings('customData.snto', [
            'openid' => $openid,
            'createdAt' => time(),
        ]);

        $device_uid = Request::str('device');

        $url = Util::murl('entry', ['device' => $device_uid, 'from' => 'device']);
        Response::redirect($url);
    }

    Log::debug('snto', [
        'raw' => Request::raw(),
    ]);

    if (Request::has('app_id')) {
        $result = [
            'app_id' => Request::str('app_id'),
            'order_id' => Request::str('order_id'),
            'params' => Request::str('params', '', true),
            'sign' => Request::str('sign'),
        ];
    } else {
        parse_str(Request::raw(), $result);
    }

    if ($result['app_id'] && $result['sign']) {
        SNTOAccount::cb($result);
        exit(SNTOAccount::RESPONSE_STR);
    } else {
        exit('数据异常！');
    }

}

exit('未启用！');