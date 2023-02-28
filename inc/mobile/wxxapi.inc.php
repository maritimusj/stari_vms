<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxxApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\Request;

$op = ucfirst(Request::op('default'));

router::exec($op, [
    'Login' => '\zovye\api\wxx\common::login',
    'VoucherList' => '\zovye\api\wxx\common::voucherList',
    'PageInfo' => '\zovye\api\wxx\common::pageInfo',
    'GetGoodsList' => '\zovye\api\wxx\common::getGoodsList',
    'Advs' => '\zovye\api\wxx\common::ads',
    'OnConnected' => '\zovye\api\wxx\common::onConnected',
    'DeviceStatus' => '\zovye\api\wxx\common::deviceStatus',
    'OnDeviceData' => '\zovye\api\wxx\common::onDeviceData',
    'VoucherGet' => '\zovye\api\wxx\common::voucherGet',
    'OrderCreate' => '\zovye\api\wxx\common::orderCreate',
    'OrderGet' => '\zovye\api\wxx\common::orderGet',
    'OrderStats' => '\zovye\api\wxx\common::orderStats',
    'GetDeviceInfo' => '\zovye\api\wxx\common::getDeviceInfo',
    'FBPic' => '\zovye\api\wxx\common::FBPic',
    'FeedBack' => '\zovye\api\wxx\common::feedback',
    'DeviceAdvs' => '\zovye\api\wxx\common::deviceAdvs',
    'OrderDefault' => '\zovye\api\wxx\common::orderDefault',
    'HomepageDefault' => '\zovye\api\wxx\common::homepageDefault',
    'HomepageOrderStat' => '\zovye\api\wxx\common::homepageOrderStat',
    'AliAuthCode' => '\zovye\api\wxx\common::aliAuthCode',
    'AliUserInfo' => '\zovye\api\wxx\common::aliUserInfo',
    'UserOrders' => '\zovye\api\wxx\common::userOrders',
]);
