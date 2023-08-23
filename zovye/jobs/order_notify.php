<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refund;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\Device;
use zovye\Helper;
use zovye\JobException;
use zovye\Log;
use zovye\Order;
use zovye\Request;
use zovye\Wx;

$op = Request::op('default');
$log = [
    'id' => Request::int('id'),
    'device_id' => Request::int('device_id'),
    'order' => Request::str('order'),
    'goods' => Request::str('goods'),
    'time' => Request::str('time'),
];

if ($op == 'order_notify' && CtrlServ::checkJobSign($log)) {

    //通过微信模板消息给代理商推送消息
    if ($log['id']) {
        $order = Order::get($log['id']);
        if (empty($order)) {
            throw new JobException('找不到这个订单！', $log);
        }

        $log['result'] = Order::sendTemplateMsg($order);

    } elseif ($log['device_id']) {

        $device = Device::get($log['device_id']);
        if (empty($device)) {
            throw new JobException('找不到这个设备！', $log);
        }

        $log['device'] = $device->profile();

        Helper::sendWxPushMessageTo($device, Order::EVENT_FAILED, [
            'character_string2' => ['value' => Wx::trim_character($log['order'])],
            'character_string1' => [
                'value' => Wx::trim_character($device->getImei()),
            ],
            'thing3' => ['value' => Wx::trim_thing($log['goods'])],
            'time4' => ['value' => $log['time']],
        ]);
    }
} else {
    $log['error'] = '签名失败！';
}

Log::debug('order_notify', $log);
