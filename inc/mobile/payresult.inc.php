<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'default') {
    $order_no = request::str('orderNO');
    $device = Device::get(request::int('deviceid'));

    app()->payResultPage($order_no, $device);

} elseif ($op == 'SQB') {

    if (request::trim('is_success') == 'T' && request::str('status') == 'SUCCESS') {
        Util::resultAlert('支付成功！');
    }

    Util::resultAlert(request::trim('error_message', '支付失败！'), 'error');

} elseif ($op == 'notify') {

    Log::debug('payresult', [
        'from' => $_GET['from'],
        'raw' => request::raw(),
    ]);

    $res = Pay::notify($_GET['from'], request::raw());

    exit($res);
}

