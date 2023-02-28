<?php


/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\fuelingTimeout;

use zovye\CtrlServ;
use zovye\Fueling;
use zovye\Log;

use zovye\Order;
use zovye\Request;

$uid = Request::str('uid');
$time = Request::int('time');

$params = [
    'uid' => $uid,
    'time' => $time,
];

$op = Request::op('default');
if ($op == 'fueling_stop_timeout' && CtrlServ::checkJobSign($params)) {

    $order = Order::get($uid, true);
    if ($order) {
        if (!$order->isFuelingFinished()) {
            $chargerID = $order->getChargerID();
            Fueling::end($uid, $chargerID, function ($order) {
                $order->setSrc(Order::FUELING);

                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '没有收到计费信息！',
                ]);
            });
        }
    }
}

Log::debug('fueling_stop_timeout', $params);