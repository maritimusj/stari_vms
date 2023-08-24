<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\accountMsg;

defined('IN_IA') or exit('Access Denied');

//公众号消息推送

use Exception;
use zovye\account\WeiSureAccount;
use zovye\CtrlServ;
use zovye\JobException;
use zovye\Log;
use zovye\Request;

$log = [
    'user' => Request::str('user'),
    'device' => Request::str('device'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!');
}

try {
    $out_user_id = base64_encode("{$log['user']}:{$log['device']}");
    WeiSureAccount::cb([
        'outerUserId' => $out_user_id,
    ], true);

} catch(Exception $e) {
    $log['error'] = $e->getMessage();
}

Log::debug('weisure', $log);