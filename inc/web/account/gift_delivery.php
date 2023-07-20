<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\gift_logModelObj;

$id = Request::int('id');
if (empty($id)) {
    Response::data(err('找不到这个记录！'));
}

/** @var gift_logModelObj $log */
$log = FlashEgg::getGiftLog($id);
if (empty($log)) {
    Response::data(err('找不到这个记录！'));
}

$fn = Request::trim('fn');
if (empty($fn)) {
    Response::templateJSON(
        'web/account/log_data',
        '物流信息',
        [
            'log' => $log,
        ]
    );
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