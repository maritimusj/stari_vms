<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\PaymentConfig;
use zovye\util\Helper;
use zovye\util\SQBUtil;

defined('IN_IA') or exit('Access Denied');

$app_id = Request::trim('app_id');
$vendor_sn = Request::trim('vendor_sn');
$vendor_key = Request::trim('vendor_key');
$code = Request::trim('code');

$result = SQBUtil::activate($app_id, $vendor_sn, $vendor_key, $code);

if (is_error($result)) {
    JSON::fail($result);
}

$config = PaymentConfig::createOrUpdate(0, Pay::SQB, [
    'sn' => $result['terminal_sn'],
    'key' => $result['terminal_key'],
    'title' => $result['store_name'],
]);

if ($config) {
    JSON::success('成功！');
}

JSON::success('失败！');