<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

JfbAccount::cb([
    'openid' => request::str('open_id'),
    'device' => request::str('facility_id'),
    'op_type' => request::int('op_type'),
    'sign' => request::str('sign'),
]);

echo JfbAccount::CB_RESPONSE;