<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (request::is_get()) {
    Util::resultAlert('出货成功，如果未领取到商品，请扫描二维码重试！');
}

MeiPaAccount::cb([
    'time' => request::str('time'),
    'apiid' => request::str('apiid'),
    'openid' => request::str('openid'),
    'carry_data' => request::str('carry_data'),
    'subscribe' => request::str('subscribe'),
    'order_sn' => request::str('order_sn'),
    'sing' => request::str('sing'),
]);

exit(REQUEST_ID);