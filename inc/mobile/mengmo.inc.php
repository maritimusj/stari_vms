<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

MengMoAccount::cb([
    'open_id' => request::str('open_id'),
    'facility_id' => request::str('facility_id'),
    'op_type' => request::int('op_type'),
    'ad_code_no' => request::str('ad_code_no'),
    'qr_code_url' => request::str('qr_code_url'),
    'wx_id' => request::str('wx_id'),
    'subscribe_time' => request::int('subscribe_time'),
    'sign' => request::str('sign'),
]);