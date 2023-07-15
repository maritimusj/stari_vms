<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxxApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\api\wxx\common;
use zovye\Request;

$op = ucfirst(Request::op('default'));

router::exec($op, [
    'Login' => [common::class, 'login'],
    'VoucherList' => [common::class, 'voucherList'],
    'PageInfo' => [common::class, 'pageInfo'],
    'GetGoodsList' => [common::class, 'getGoodsList'],
    'Advs' => [common::class, 'ads'],
    'OnConnected' => [common::class, 'onConnected'],
    'DeviceStatus' => [common::class, 'deviceStatus'],
    'OnDeviceData' => [common::class, 'onDeviceData'],
    'VoucherGet' => [common::class, 'voucherGet'],
    'OrderCreate' => [common::class, 'orderCreate'],
    'OrderGet' => [common::class, 'orderGet'],
    'OrderStats' => [common::class, 'orderStats'],
    'GetDeviceInfo' => [common::class, 'getDeviceInfo'],
    'FBPic' => [common::class, 'FBPic'],
    'FeedBack' => [common::class, 'feedback'],
    'DeviceAdvs' => [common::class, 'deviceAds'],
    'OrderDefault' => [common::class, 'orderDefault'],
    'HomepageDefault' => [common::class, 'homepageDefault'],
    'HomepageOrderStat' => [common::class, 'homepageOrderStat'],
    'AliAuthCode' => [common::class, 'aliAuthCode'],
    'AliUserInfo' => [common::class, 'aliUserInfo'],
    'UserOrders' => [common::class, 'userOrders'],
]);
