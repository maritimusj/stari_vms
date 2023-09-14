<?php


/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\chargingTimeout;

defined('IN_IA') or exit('Access Denied');

use zovye\business\Charging;
use zovye\CtrlServ;
use zovye\domain\Order;
use zovye\JobException;
use zovye\Log;
use zovye\Request;

$uid = Request::str('uid');
$time = Request::int('time');

$log = [
    'uid' => $uid,
    'time' => $time,
];

if (!CtrlServ::checkJobSign($log)) {
    throw new JobException('签名不正确!', $log);
}

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

Log::debug('charging_stop_timeout', $log);