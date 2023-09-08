<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$priceFN = function ($is_floating, &$data) {
    if ($data['cargo_lanes']) {
        foreach ((array)$data['cargo_lanes'] as $index => $lane) {
            $data['cargo_lanes'][$index]['goods_price'] = number_format($lane['goods_price'] / 100, 2);
            if (!isset($lane['num'])) {
                $data['cargo_lanes'][$index]['num'] = 0;
            } else {
                if ($is_floating) {
                    $data['cargo_lanes'][$index]['num'] = round($lane['num'] / 100, 2);
                }
            }
            if ($is_floating) {
                $data['cargo_lanes'][$index]['capacity'] = round($lane['capacity'] / 100, 2);
            }
        }
    }

    return $data;
};

$device_id = Request::int('deviceid');
$type_id = Request::int('typeid');

if ($device_id) {
    $device = Device::get($device_id);
}

if ($type_id) {
    $device_type = DeviceTypes::get($type_id);
    if (empty($device_type)) {
        JSON::fail('找不到这个型号！');
    }

    $data = DeviceTypes::format($device_type, true);
    if (isset($device)) {
        $payload = $device->getPayload();
        foreach ((array)$payload['cargo_lanes'] as $index => $lane) {
            $data['cargo_lanes'][$index]['num'] = intval($lane['num']);
        }
    }

    $data = $priceFN(isset($device) && $device->isFuelingDevice(), $data);

    JSON::success($data);
}


if ($device_id) {
    $device = Device::get($device_id);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $data = $priceFN($device->isFuelingDevice(), $device->getPayload(true));

    JSON::success($data);
}

JSON::success(['cargo_lanes' => []]);