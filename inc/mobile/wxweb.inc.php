<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxWebApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\request;
use zovye\Util;

Util::extraAjaxJsonData();

$op = request::op('default');

router::exec($op, [
    'login' => '\zovye\api\wxweb\api::login',
    'advs' => '\zovye\api\wxweb\api::ads',
    'accounts' => '\zovye\api\wxweb\api::accounts',
    'goods' => '\zovye\api\wxweb\api::goods',
    'get' => '\zovye\api\wxweb\api::get',
    'exchange' => '\zovye\api\wxweb\api::exchange',
    'pay' => '\zovye\api\wxweb\api::pay',
    'orderStatus' => '\zovye\api\wxweb\api::orderStatus',
    'userInfo' => '\zovye\api\wxweb\api::userInfo',
    'feedback' => '\zovye\api\wxweb\api::feedback',
    'signIn' => '\zovye\api\wxweb\api::signIn',
    'bonus' => '\zovye\api\wxweb\api::bonus',
    'rewardQuota' => '\zovye\api\wxweb\api::rewardQuota',
    'reward' => '\zovye\api\wxweb\api::reward',
    'balanceLog' => '\zovye\api\wxweb\api::balanceLog',
    'orderList' => '\zovye\api\wxweb\api::orderList',
    'task' => '\zovye\api\wxweb\api::task',
    'detail' => '\zovye\api\wxweb\api::detail',
    'submit' => '\zovye\api\wxweb\api::submit',
    'upload' => '\zovye\api\wxx\common::FBPic',
    'recipient' => '\zovye\api\wxweb\api::getRecipient',
    'updateRecipient' => '\zovye\api\wxweb\api::updateRecipient',
    'mallOrderList' => '\zovye\api\wxweb\api::getMallOrderList',
    'mallGoodsList' => '\zovye\api\wxweb\api::getMallGoodsList',
    'createMallOrder' => '\zovye\api\wxweb\api::createMallOrder',
    'pageInfo' => '\zovye\api\wxx\common::pageInfo',
    'rewardOrderData' => '\zovye\api\wxweb\api::rewardOrderData',
    'validateLocation' => '\zovye\api\wxweb\api::validateLocation',

    'chargingUserInfo' => '\zovye\api\wxweb\charging::chargingUserInfo',
    'chargingGroupList' => '\zovye\api\wxweb\charging::groupList',
    'chargingGroupDetail' => '\zovye\api\wxweb\charging::groupDetail',
    'chargingDeviceList' => '\zovye\api\wxweb\charging::deviceList',
    'chargingDeviceDetail' => '\zovye\api\wxweb\charging::deviceDetail',
    'chargingStart' => '\zovye\api\wxweb\charging::start',
    'chargingStop' => '\zovye\api\wxweb\charging::stop',
    'chargingOrderStatus' => '\zovye\api\wxweb\charging::orderStatus',
    'chargingOrderList' => '\zovye\api\wxweb\charging::orderList',
    'chargingOrderDetail' => '\zovye\api\wxweb\charging::orderDetail',
    'chargingStatus' => '\zovye\api\wxweb\charging::status',
    'payForCharging' => '\zovye\api\wxweb\charging::payForCharging',
    'chargingPayResult' => '\zovye\api\wxweb\charging::chargingPayResult',
    'payForRecharge' => '\zovye\api\wxweb\charging::payForRecharge',
    'rechargeResult' => '\zovye\api\wxweb\charging::rechargeResult',
    'rechargeList' => '\zovye\api\wxweb\charging::rechargeList',
    'withdraw' => '\zovye\api\wxweb\charging::withdraw',
]);