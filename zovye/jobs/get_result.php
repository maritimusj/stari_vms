<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\getResult;

//出货

use zovye\CtrlServ;
use zovye\request;
use zovye\Order;
use zovye\User;
use zovye\Util;
use function zovye\request;

$op = request::op('default');
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

Util::logToFile('get_result', $log);
