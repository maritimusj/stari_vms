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
        Response::alert('支付成功！');
    }

    Response::alert(Request::trim('error_message', '支付失败！'), 'error');

} elseif ($op == 'notify') {

    Log::debug('payresult', [
        'from' => $_GET['from'],
        'raw' => Request::raw(),
    ]);

    $res = Pay::notify($_GET['from'], Request::raw());

    exit($res);
}

