<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxxApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\common;
use zovye\api\router;
use zovye\api\wxx\bluetooth;
use zovye\Request;

$op = ucfirst(Request::op('default'));

router::exec($op, [
    'Login' => [common::class, 'login'],
    'FBPic' => [common::class, 'upload'],
    'PageInfo' => [common::class, 'pageInfo'],
    'VoucherList' => [bluetooth::class, 'voucherList'],
    'GetGoodsList' => [bluetooth::class, 'getGoodsList'],
    'Advs' => [bluetooth::class, 'ads'],
    'OnConnected' => [bluetooth::class, 'onConnected'],
    'DeviceStatus' => [bluetooth::class, 'deviceStatus'],
    'OnDeviceData' => [bluetooth::class, 'onDeviceData'],
    'VoucherGet' => [bluetooth::class, 'voucherGet'],
    'OrderCreate' => [bluetooth::class, 'orderCreate'],
    'OrderGet' => [bluetooth::class, 'orderGet'],
    'OrderStats' => [bluetooth::class, 'orderStats'],
    'GetDeviceInfo' => [bluetooth::class, 'getDeviceInfo'],
    'FeedBack' => [bluetooth::class, 'feedback'],
    'DeviceAdvs' => [bluetooth::class, 'deviceAds'],
    'OrderDefault' => [bluetooth::class, 'orderDefault'],
    'HomepageDefault' => [bluetooth::class, 'homepageDefault'],
    'HomepageOrderStat' => [bluetooth::class, 'homepageOrderStat'],
    'AliAuthCode' => [bluetooth::class, 'aliAuthCode'],
    'AliUserInfo' => [bluetooth::class, 'aliUserInfo'],
    'UserOrders' => [bluetooth::class, 'userOrders'],
]);
