<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$iccid = Request::str('iccid');
if (empty($iccid)) {
    JSON::fail('错误：iccid 为空！');
}

$result = SIM::get($iccid);
if (is_error($result)) {
    JSON::fail($result);
}

$content = app()->fetchTemplate(
    'web/device/card_status',
    [
        'card' => $result,
    ]
);

JSON::success(['title' => "流量卡状态", 'content' => $content,]);