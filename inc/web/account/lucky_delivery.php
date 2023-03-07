<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\lucky_logModelObj;

defined('IN_IA') or exit('Access Denied');

$id = request::int('id');
if (empty($id)) {
    Util::resultData(err('找不到这个记录！'));
}

/** @var lucky_logModelObj $log */
$log = FlashEgg::getLuckyLog($id);
if (empty($log)) {
    Util::resultData(err('找不到这个记录！'));
}

$fn = Request::trim('fn');
if (empty($fn)) {
    $content = app()->fetchTemplate('web/account/log_data', [
        'log' => $log->format(true),
    ]);
    JSON::success(['title' => '', 'content' => $content]);
}

if ($fn == 'save') {
    $log->setStatus(Request::int('status'));
    $log->setExtraData('delivery', [
        'name' => Request::trim('deliveryName'),
        'sn' => Request::trim('deliverySN'),
        'memo' => Request::trim('memo'),
    ]);
    if ($log->save()) {
        JSON::success([
            'status' => $log->getStatus(),
            'memo' => $log->getExtraData('delivery.memo', ''),
        ]);
    }
}

JSON::fail('未知请求！');