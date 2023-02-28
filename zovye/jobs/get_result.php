<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\getResult;

//出货

use zovye\CtrlServ;
use zovye\Log;
use zovye\Order;
use zovye\Request;
use zovye\User;
use function zovye\request;

$op = Request::op('default');
$data = [
    'openid' => request('openid'),
    'orderNO' => request('orderNO'),
];

$log = [
    'data' => $data,
];

if ($op == 'get_result' && CtrlServ::checkJobSign($data)) {
    $user = User::get($data['openid'], true);
    if (empty($user)) {
        $log['error'] = 'user not exists.';
    } else {
        $order_no = strval($data['orderNO']);
        if (!Order::exists($order_no)) {
            $this->payResultBy($user, $order_no);
        }
    }
}

Log::debug('get_result', $log);
