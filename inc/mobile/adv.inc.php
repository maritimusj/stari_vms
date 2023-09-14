<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use zovye\business\TKPromoting;
use zovye\domain\Advertising;
use zovye\domain\Device;
use zovye\util\DeviceUtil;
use zovye\util\PlaceHolder;

$user = Session::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法领取');
}

if (Request::has('typeid')) {
    $type_id = Request::int('typeid');
} else {
    $type = Request::trim('type');
    $type_id = Advertising::getTypeId($type);
}

$num = Request::int('num', 10);

if (Request::has('deviceid')) {
    $device = Device::get(Request::str('deviceid'), true);
    if (empty($device)) {
        JSON::fail('找不到这个设备');
    }
} else {
    $device = Device::getDummyDevice();
}

$result = [];

$params = [$user, $device, new DateTime()];

if (App::isTKPromotingEnabled() && $type_id == Advertising::WELCOME_PAGE) {
    $account = TKPromoting::getAccount();
    if (!is_error($account)) {
        $res = Helper::checkAvailable($user, $account, $device);
        if (!is_error($res)) {
            $result[] = TKPromoting::getAd();
            $params['tk_user_uid'] = TKPromoting::getUserPrefix() . $user->getOpenid();
        }
    }
}

if (empty($result)) {
    $result = DeviceUtil::getAds($device, $type_id, $num);
    if (is_error($result)) {
        JSON::fail($result);
    }
}

foreach ($result as $index => $ad) {
    if ($ad['data']) {
        if ($ad['data']['url']) {
            $result[$index]['data']['url'] = PlaceHolder::replace($ad['data']['url'], $params);
        }
        if ($ad['data']['link']) {
            $result[$index]['data']['link'] = PlaceHolder::replace($ad['data']['link'], $params);
        }
    }
}

JSON::success($result);
