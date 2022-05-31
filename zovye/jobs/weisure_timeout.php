<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\accountMsg;

//公众号消息推送

use zovye\CtrlServ;
use zovye\Log;
use zovye\request;
use Exception;
use zovye\WeiSureAccount;

$data = [
    'user' => request::str('user'),
    'device' => request::str('device'),
];

$op = request::op('default');

if ($op == 'weisure_timeout' && CtrlServ::checkJobSign($data)) {

    try {
        $outUserId = base64_encode("{$data['user']}:{$data['device']}");
        WeiSureAccount::cb([
            'outerUserId' => $outUserId,
        ], true);

    } catch(Exception $e) {
        $data['error'] = $e->getMessage();
    }
} else {
    $data['error'] = '签名不正确！';
}

Log::debug('weisure', $data);