<?php


/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\fuelingTimeout;

defined('IN_IA') or exit('Access Denied');

use zovye\business\Fueling;
use zovye\CtrlServ;
use zovye\domain\Order;
use zovye\JobException;
use zovye\Log;
use zovye\Request;
use function zovye\is_error;

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
    if (!$order->isFuelingFinished()) {

        $device = $order->getDevice();
        $chargerID = $order->getChargerID();

        $result = Fueling::settle($device, [
            'ser' => $order->getOrderNO(),
            'ch' => $chargerID,
            'reason' => -1,
            'solo' => Fueling::MODE_REMOTE,
            'time' => time(),
        ]);

        if (is_error($result)) {
            Log::error('fueling', [
                'job' => 'fueling_stop_timeout',
                'uid' => $uid,
                'time' => $time,
                'error' => $result,
            ]);
        }

        $order->setExtraData('timeout', [
            'at' => time(),
            'reason' => '没有收到计费信息！',
        ]);

        $order->save();
    }
}

Log::debug('fueling_stop_timeout', $log);