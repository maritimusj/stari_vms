<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');

if ($op == 'default') {
    $order_no = Request::str('orderNO');
    $device = Device::get(Request::int('deviceid'));

    app()->payResultPage($order_no, $device);

} elseif ($op == 'SQB') {

    if (Request::trim('is_success') == 'T' && Request::str('status') == 'SUCCESS') {
        Util::resultAlert('支付成功！');
    }

    Util::resultAlert(Request::trim('error_message', '支付失败！'), 'error');

} elseif ($op == 'notify') {

    Log::debug('payresult', [
        'from' => $_GET['from'],
        'raw' => Request::raw(),
    ]);

    $res = Pay::notify($_GET['from'], Request::raw());

    exit($res);
}

