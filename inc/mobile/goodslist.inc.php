<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

$ticket = request::str('ticket');
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

$payload = $device->getPayload(true);
$result = $payload['cargo_lanes'];

$goods = [];
foreach ($result as $entry) {
    if (request::bool('free') && $entry[Goods::AllowFree] or
        request::bool('pay') && $entry[Goods::AllowPay] or
        empty(request('free')) &&
        empty(request('pay'))) {

        $key = "goods{$entry['goods_id']}";
        if ($goods[$key]) {
            $goods[$key]['num'] += intval($entry['num']);
        } else {
            $goods[$key] = [
                'id' => $entry['goods_id'],
                'name' => $entry['goods_name'],
                'img' => Util::toMedia($entry['goods_img'], true),
                'num' => intval($entry['num']),
                'allow_free' => $entry[Goods::AllowFree],
                'allow_pay' => $entry[Goods::AllowPay],
            ];
        }
    }
}

JSON::success(['title' => '请选择商品', 'goods' => array_values($goods)]);
