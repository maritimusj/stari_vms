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
    $device = Device::getBalanceVDevice();
}

$result = Util::getDeviceAdvs($device, $type_id, $num);
if (is_error($result)) {
    JSON::fail($result);
}

//对广告链接中的特殊点位符进行替换
$replacer = function ($url) use ($user, $device) {
    return str_ireplace(['{user_uid}', '{device_uid}'], [$user->getOpenid(), $device->getShadowId()], $url);
};

foreach ($result as $index => $adv) {
    if ($adv['data']) {
        if ($adv['data']['url']) {
            $result[$index]['data']['url'] = $replacer($adv['data']['url']);
        }
        if ($adv['data']['link']) {
            $result[$index]['data']['link'] = $replacer($adv['data']['link']);
        }
    }
}

JSON::success($result);
