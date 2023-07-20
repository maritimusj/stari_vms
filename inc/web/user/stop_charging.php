<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\charging_now_dataModelObj;

$user = User::get(Request::int('id'));
if (empty($user)) {
    JSON::fail('找不到这个用户！');
}

$fn = Request::str('fn');
if (empty($fn)) {
    if (empty(ChargingNowData::countByUser($user))) {
        JSON::fail('没有发现用户充电信息！');
    }

    $list = [];

    /** @var charging_now_dataModelObj $charging_now_data */
    foreach (ChargingNowData::getAllByUser($user) as $charging_now_data) {

        $serial = $charging_now_data->getSerial();
        $device = $charging_now_data->getDevice();
        $order = Order::get($serial, true);
        $list[] = [
            'serial' => $serial,
            'chargerID' => $charging_now_data->getChargerId(),
            'device' => $device ?? $device->profile(),
            'order' => $order ?? $order->profile(),
        ];
    }

    Response::templateJSON(
        'web/user/charging', '充电信息',
        [
            'user' => $user->profile(),
            'list' => $list,
        ]
    );

} elseif ($fn == 'stop') {

    $serial = Request::str('serial');

    $charging_now_data = ChargingNowData::getByUser($user, $serial);

    if (empty($charging_now_data)) {
        JSON::fail('找不到这个充电记录！');
    }

    $result = Charging::stop($user, $serial);

    JSON::result($result);
}
