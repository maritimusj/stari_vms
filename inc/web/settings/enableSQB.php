<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$app_id = request::trim('app_id');
$vendor_sn = request::trim('vendor_sn');
$vendor_key = request::trim('vendor_key');
$code = request::trim('code');

$result = SQB::activate($app_id, $vendor_sn, $vendor_key, $code);

if (is_error($result)) {
    JSON::fail($result);
}

if (false === Util::createApiRedirectFile('/payment/SQB.php', 'payresult', [
        'headers' => [
            'HTTP_USER_AGENT' => 'SQB_notify',
        ],
        'op' => 'notify',
        'from' => 'SQB',
    ])) {
    Util::itoast('创建收钱吧支付入口文件失败！');
}

if (updateSettings('pay.SQB', [
    'enable' => 1,
    'wx' => request::bool('wx'),
    'ali' => request::bool('ali'),
    'sn' => $result['terminal_sn'],
    'key' => $result['terminal_key'],
    'title' => $result['store_name'],
])) {
    JSON::success('成功！');
}

JSON::success('失败！');