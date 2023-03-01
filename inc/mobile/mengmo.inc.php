<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\MengMoAccount;

MengMoAccount::cb([
    'open_id' => Request::str('open_id'),
    'facility_id' => Request::str('facility_id'),
    'op_type' => Request::int('op_type'),
    'ad_code_no' => Request::str('ad_code_no'),
    'qr_code_url' => Request::str('qr_code_url'),
    'wx_id' => Request::str('wx_id'),
    'subscribe_time' => Request::int('subscribe_time'),
    'sign' => Request::str('sign'),
]);