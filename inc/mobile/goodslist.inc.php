<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Device;
use zovye\domain\Goods;

defined('IN_IA') or exit('Access Denied');

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

$ticket = Request::str('ticket');
if (empty($ticket)) {
    JSON::fail('请重新扫描设备二维码 [701]');
}

$ticket_data_saved = $user->getLastActiveData('ticket', []);
if (empty($ticket_data_saved) || $ticket !== $ticket_data_saved['id'] ||
    time() - $ticket_data_saved['time'] > settings('user.scanAlive', VISIT_DATA_TIMEOUT)) {
    JSON::fail('请重新扫描设备二维码 [702]');
}

if (empty($ticket_data_saved['deviceId'])) {
    JSON::fail('请重新扫描设备二维码 [703]');
}

$device = Device::find($ticket_data_saved['deviceId'], ['id', 'imei', 'shadow_id']);
if (empty($device)) {
    JSON::fail('请重新扫描设备二维码 [704]');
}

$payload = $device->getPayload(true, true);
$result = $payload['cargo_lanes'] ?? [];

$allow_free = Request::bool('free');
$allow_pay = Request::bool('pay');

$goods = [];
foreach ($result as $entry) {
    if ($allow_free && $entry[Goods::AllowFree] or $allow_pay && $entry[Goods::AllowPay] or !$allow_free && !$allow_pay) {
        $key = "goods{$entry['goods_id']}";
        if ($goods[$key]) {
            $goods[$key]['num'] += intval($entry['num']);
        } else {
            $goods[$key] = [
                'id' => $entry['goods_id'],
                'name' => $entry['goods_name'],
                'img' => $entry['goods_img'],
                'num' => intval($entry['num']),
                'allow_free' => $entry[Goods::AllowFree],
                'allow_pay' => $entry[Goods::AllowPay],
            ];
        }
    }
}

JSON::success(['title' => '请选择商品', 'goods' => array_values($goods)]);
