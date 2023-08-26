<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$app_id = Request::trim('app_id');
$vendor_sn = Request::trim('vendor_sn');
$vendor_key = Request::trim('vendor_key');
$code = Request::trim('code');

$result = SQB::activate($app_id, $vendor_sn, $vendor_key, $code);

if (is_error($result)) {
    JSON::fail($result);
}

if (false === Helper::createApiRedirectFile('/payment/SQB.php', 'payresult', [
        'headers' => [
            'HTTP_USER_AGENT' => 'SQB_notify',
        ],
        'op' => 'notify',
        'from' => 'SQB',
    ])) {
    Response::toast('创建收钱吧支付入口文件失败！');
}

if (updateSettings('pay.SQB', [
    'enable' => 1,
    'wx' => Request::bool('wx'),
    'wxapp' => Request::bool('wxapp'),    
    'ali' => Request::bool('ali'),
    'sn' => $result['terminal_sn'],
    'key' => $result['terminal_key'],
    'title' => $result['store_name'],
])) {
    JSON::success('成功！');
}

JSON::success('失败！');