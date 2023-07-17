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
use function zovye\is_error;

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
}

Log::debug('fueling_stop_timeout', $params);