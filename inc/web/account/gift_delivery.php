<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\gift_logModelObj;

defined('IN_IA') or exit('Access Denied');

$id = request::int('id');
if (empty($id)) {
    Util::resultData(err('找不到这个记录！'));
}

/** @var gift_logModelObj $log */
$log = FlashEgg::getGiftLog($id);
if (empty($log)) {
    Util::resultData(err('找不到这个记录！'));
}

$fn = Request::trim('fn');
if (empty($fn)) {
    $content = app()->fetchTemplate('web/account/log_data', [
        'log' => $log,
    ]);
    JSON::success(['title' => '物流信息', 'content' => $content]);
}

if ($fn == 'save') {
    $log->setStatus(Request::int('status'));

    $log->setExtraData('delivery', [
        'name' => Request::trim('deliveryName'),
        'sn' => Request::trim('deliverySN'),
        'memo' => Request::trim('memo'),
    ]);

    $log->setStatus(Request::bool('status') ? 1 : 0);

    if ($log->save()) {
        JSON::success([
            'status' => $log->getStatus(),
            'delivery' => $log->getExtraData('delivery', []),
        ]);
    }
}

JSON::fail('未知请求！');