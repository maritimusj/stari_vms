<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (App::isSNTOEnabled()) {
    SNTOAccount::cb([
        'app_id' => request::str('app_id'),
        'order_id' => request::str('order_id'),
        'params' => request::str('params', '', true),
        'sign' => request::str('sign'),
    ]);
}

exit(SNTOAccount::RESPONSE_STR);