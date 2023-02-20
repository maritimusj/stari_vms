<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\userModelObj;

defined('IN_IA') or exit('Access Denied');

if (!App::isFlashEggEnabled()) {
    Util::resultAlert('该功能没有启用，请联系管理员，谢谢！', 'error');
}

$op = request::op('default');

//获取关注公众号二维码
if ($op == 'qrcode') {
    $res = Wx::getTempQRCodeTicket();
    if ($res && $res['ticket']) {
        JSON::success([
            'url' => 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='. $res['ticket'],
        ]);
    }
    JSON::fail('无法获取公众号二维码！');
}

//获取商品列表
if ($op == 'goods') {

}

$params = [
    'create' => true,
    'update' => true,
];

$getUserFN = function () use (&$params) {
    $user = Util::getCurrentUser($params);
    if (empty($user)) {
        Util::resultAlert('请用微信或者支付宝扫描二维码，谢谢！', 'error');
    }

    if ($user->isBanned()) {
        Util::resultAlert('用户暂时无法使用该功能，请联系管理员！', 'error');
    }
};

$device_uid = request::str('device');
if ($device_uid) {
    $device = Device::get($device_uid, true);
}

if (empty($device)) {
    /** @var userModelObj $user */
    $user = $getUserFN();
    $device = $user->getLastActiveDevice();
    if (empty($device)) {
        Util::resultAlert('请重新扫描设备二维码！', 'error');
    }
}

$params['from'] = [
    'src' => 'device',
    'device' => [
        'name' => $device->getName(),
        'imei' => $device->getImei(),
    ],
    'ip' => CLIENT_IP,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
];

/** @var userModelObj $user */
$user = $getUserFN();

//记录设备ID
$user->setLastActiveDevice($device);

if (!$GLOBALS['_W']['fans']['follow']) {
    app()->followPage($user, $device);
}

app()->goodsListPage($user, $device);

