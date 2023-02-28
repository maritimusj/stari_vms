<?php


/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\chargingTimeout;

use zovye\Charging;
use zovye\CtrlServ;
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
if ($op == 'charging_stop_timeout' && CtrlServ::checkJobSign($params)) {

    $order = Order::get($uid, true);
    if ($order) {
        if (!$order->isChargingFinished()) {
            $chargerID = $order->getChargerID();
            Charging::end($uid, $chargerID, function ($order) {
                $order->setExtraData('timeout', [
                    'at' => time(),
                    'reason' => '没有收到充电桩帐单通知！',
                ]);
            });
        }
    }
}

Log::debug('charging_stop_timeout', $params);