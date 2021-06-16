<?php
namespace zovye;

defined('IN_IA') or exit('Access Denied');

MeiPaAccount::cb([
    'time' => request::str('time'),
    'apiid' => request::str('apiid'),
    'openid' => request::str('openid'),
    'carry_data' => request::str('carry_data'),
    'subscribe' => request::str('subscribe'),
    'order_sn' => request::int('order_sn'),
    'sing' => request::str('sing'),
]);
