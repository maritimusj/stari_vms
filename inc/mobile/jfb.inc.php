<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

JfbAccount::cb([
    'openid' => request::str('open_id'),
    'device' => request::str('facility_id'),
    'op_type' => request::int('op_type'),
    'ad_code_no' => request::str('ad_code_no'),
]);

echo JfbAccount::CB_RESPONSE;