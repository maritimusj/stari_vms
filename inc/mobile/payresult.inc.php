<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'default') {
    $order_no = Request::str('orderNO');
    $device = Device::get(Request::int('deviceid'));

    Response::payResultPage([
        'order_no' => $order_no,
        'device' => $device,
    ]);

} elseif ($op == 'SQB') {

    if (Request::trim('is_success') == 'T' && Request::str('status') == 'SUCCESS') {
        $order_no = Request::str('orderNO');
        $device = Device::get(Request::int('deviceid'));

        Response::payResultPage([
            'order_no' => $order_no,
            'device' => $device,
        ]);
    }

    Response::alert(Request::trim('error_message', '支付失败！'), 'error');

} elseif ($op == 'notify') {

    Log::debug('payresult', [
        'from' => $_GET['from'],
        'agent_id' => $_GET['config_id'],
        'config_id' => $_GET['config_id'],
        'raw' => Request::raw(),
    ]);

    $response = Pay::notify($_GET['config_id'], Request::raw());

    Response::echo($response);
}

