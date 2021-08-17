<?php

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (App::isSNTOEnabled()) {
    SNTOAccount::cb([
        'app_id' => request::str('app_id'),
        'order_id' => request::str('order_id'),
        'mac' => request::str('mac', '', true),
        'sign' => request::str('sign'),
    ]);
}

exit(SNTOAccount::RESPONSE_STR);