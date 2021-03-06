<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

if (request::has('typeid')) {
    $type_id = request::int('typeid');
} else {
    $type = request::trim('type');
    $type_id = Advertising::getTypeId($type);
}

$num = request::int('num', 10);

if (request::has('deviceid')) {
    $device = Device::get(request::str('deviceid'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备');
    }
} else {
    $device = Device::getDummyDevice();
}

$result = Util::getDeviceAds($device, $type_id, $num);
if (is_error($result)) {
    JSON::fail($result);
}

$params = [$user, $device, new DateTime()];

foreach ($result as $index => $adv) {
    if ($adv['data']) {
        if ($adv['data']['url']) {
            $result[$index]['data']['url'] = PlaceHolder::replace($adv['data']['url'], $params);
        }
        if ($adv['data']['link']) {
            $result[$index]['data']['link'] = PlaceHolder::replace($adv['data']['link'], $params);
        }
    }
}

JSON::success($result);
