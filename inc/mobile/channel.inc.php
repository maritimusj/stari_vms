<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

$op = request::op('default');

if ($op == 'default') {
    if (!App::isChannelPayEnabled()) {
        JSON::fail('没有启用该功能！');
    }

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('用户无法使用该功能！');
    }

    $device_uid = request::str('device');
    $device = Device::findOne(['shadow_id' => $device_uid]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $goods_id = request::int('goods');
    $goods = Goods::get($goods_id);
    if (empty($goods)) {
        JSON::fail('找不到指定商品！');
    }

    $num = request::int('num', 1);
    if ($num < 1) {
        JSON::fail('商品数量不对！');
    }

    $result = ChannelPay::createOrder($device, $user, $goods, $num);
    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success($result);    

} elseif ($op == 'result') {

    $appResult = request::trim('appResult');
    if ($appResult == 'success') {
        Util::resultAlert('出货成功！');
    } else {
        Util::resultAlert('出货失败！', 'error');
    }
}
