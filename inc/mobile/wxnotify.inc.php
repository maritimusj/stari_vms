<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (Request::is_get()) {
    $data = [
        'signature' => Request::str('signature'),
        'nonce' => Request::str('nonce'),
        'timestamp' => Request::str('timestamp'),
    ];

    $passed = WxAppMessagePush::verify($data);
    if ($passed) {
        echo Request::str('echostr');
        exit();
    }
    Log::error('wxnotify', [
        'data' => $data,
        'error' => 'Token验证失败！',
    ]);
    exit();
}

$json_data = Request::json();
$result = WxAppMessagePush::handle($json_data);

if (is_error($result)) {
    Log::error('wxnotify', [
        'data' => $json_data,
        'error' => $result,
    ]);
}

echo WxAppMessagePush::RESPONSE;