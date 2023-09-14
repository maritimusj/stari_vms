<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\getResult;

defined('IN_IA') or exit('Access Denied');

//出货

use zovye\CtrlServ;
use zovye\domain\Order;
use zovye\domain\User;
use zovye\JobException;
use zovye\Log;
use zovye\Request;

$log = [
    'openid' => Request::str('openid'),
    'orderNO' => Request::str('orderNO'),
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

$user = User::get($log['openid'], true);
if (empty($user)) {
    $log['error'] = 'user not exists.';
} else {
    $order_no = strval($log['orderNO']);
    if (!Order::exists($order_no)) {
        $this->payResultBy($user, $order_no);
    }
}
Log::debug('get_result', $log);
