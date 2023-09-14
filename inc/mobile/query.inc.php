<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Device;
use zovye\domain\Order;
use zovye\domain\User;

defined('IN_IA') or exit('Access Denied');

$op = Request::str('q');
$ts = Request::int('ts');
$sign = Request::str('sign');

if (abs(time() - $ts) > 3600 || empty($sign) || empty($op)) {
    exit('invalid request.');
}

$secret = Config::api('app.secret');
if (empty($secret)) {
    exit('invalid app secret.');
}

if ($sign != sha1("$op$ts$secret")) {
    exit('invalid sign.');
}

$op = Request::str('q');

//接口说明：请求指定用户的来源设备的运营人员信息
if ($op == 'keeper') {
    $mobile = Request::trim('mobile');
    if (empty($mobile)) {
        JSON::fail('用户手机号码不正确！');
    }
    $user = User::findOne(['mobile' => $mobile]);
    if (empty($user)) {
        JSON::fail('用户不存在！');
    }

    //从用户的来访数据获取设备
    $from_data = $user->get('fromData', []);
    if ($from_data && !empty($from_data['device'])) {
        $device = Device::get($from_data['device']['imei'], true);
    }

    if (!isset($device)) {
        //从用户的第一个订单中获取设备
        $order = Order::getFirstOrderOfUser($user, true);
        if ($order) {
            $device = $order->getDevice();
        }
    }

    if (isset($device)) {
        $keepers = $device->getKeepers();
        $result = [];
        foreach($keepers as $keeper) {
            $result[] = [
                'name' => $keeper->getName(),
                'mobile' => $keeper->getMobile(),
            ];
        }
        JSON::success($result);
    }

    JSON::fail('没有需要的数据！');
}