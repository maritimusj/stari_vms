<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$agent = Agent::get(request::int('id'));
if (empty($agent)) {
    JSON::fail('找不到这个代理商！');
}

$app_id = request::trim('app_id');
$vendor_sn = request::trim('vendor_sn');
$vendor_key = request::trim('vendor_key');
$code = request::trim('code');

$result = SQB::activate($app_id, $vendor_sn, $vendor_key, $code);

if (is_error($result)) {
    JSON::fail($result);
}

if ($agent->updateSettings('agentData.pay.SQB', [
    'enable' => 1,
    'sn' => $result['terminal_sn'],
    'key' => $result['terminal_key'],
    'title' => $result['store_name'],
])) {
    JSON::success('成功！');
}

JSON::success('失败！');