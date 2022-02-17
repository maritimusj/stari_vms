<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (request::is_get()) {
    $passed = WxAppMessagePush::verify([
        'signature' =>  request::str('signature'),
        'nonce' => request::str('nonce'),
        'timestamp' => request::str('timestamp'),
    ]);
    echo $passed ? request::str('echostr') : '';
}

$json_data = request::json();
$result = WxAppMessagePush::handle($json_data);

Log::debug('notify', [
    'message' => $json_data,
    'response' => $result,
]);

if (is_error($result)) {
    Log::error('notify', $result);
} else {
    echo WxAppMessagePush::RESPONSE;
}