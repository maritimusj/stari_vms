<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$iccid = Request::str('iccid');
if (empty($iccid)) {
    JSON::fail('错误：iccid 为空！');
}

$result = SIM::get($iccid);
if (is_error($result)) {
    JSON::fail($result);
}

Response::templateJSON(
    'web/device/card_status',
    '流量卡状态',
    [
        'card' => $result,
    ]
);