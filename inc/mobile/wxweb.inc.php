<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxWebApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\api\wxweb\fueling;
use zovye\request;
use zovye\Util;

Util::extraAjaxJsonData();

$op = request::op('default');

router::exec($op, [
    'login' => '\zovye\api\wxweb\api::login',

    'nearBy' => '\zovye\api\wxweb\api::nearBy',

    'advs' => '\zovye\api\wxweb\api::ads',
    'accounts' => '\zovye\api\wxweb\api::accounts',
    'goods' => '\zovye\api\wxweb\api::goods',
    'get' => '\zovye\api\wxweb\api::get',
    'exchange' => '\zovye\api\wxweb\api::exchange',
    'pay' => '\zovye\api\wxweb\api::pay',
    'orderStatus' => '\zovye\api\wxweb\api::orderStatus',
    'userInfo' => '\zovye\api\wxweb\api::userInfo',
    'getJumpUserInfo' => '\zovye\api\wxweb\api::getJumpUserInfo',
    'getUserBank' => '\zovye\api\wxweb\api::getUserBank',
    'setUserBank' => '\zovye\api\wxweb\api::setUserBank',
    'getUserQRcode' => '\zovye\api\wxweb\api::getUserQRCode',
    'updateUserQRcode' => '\zovye\api\wxweb\api::updateUserQRCode',
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
    'withdraw' => '\zovye\api\wxweb\charging::withdraw',

    'payForRecharge' => '\zovye\api\wxweb\user::payForRecharge',
    'rechargeResult' => '\zovye\api\wxweb\user::rechargeResult',
    'rechargeList' => '\zovye\api\wxweb\user::rechargeList',

    'memberList' => '\zovye\api\wxweb\member::getMemberList',
    'memberUserInfo' => '\zovye\api\wxweb\member::memberUserInfo',
    'createMember' => '\zovye\api\wxweb\member::createMember',
    'editMember' => '\zovye\api\wxweb\member::editMember',
    'removeMember' => '\zovye\api\wxweb\member::removeMember',
    'transfer' => '\zovye\api\wxweb\member::transfer',
    'memberOrderList' => '\zovye\api\wxweb\member::orderList',
    'memberOrderDetail' => '\zovye\api\wxweb\member::orderDetail',
    'memberChargingList' => '\zovye\api\wxweb\member::chargingList',

    'fuelingRechargeInfo' => '\zovye\api\wxweb\fueling::rechargeInfo',
    'fuelingStart' => '\zovye\api\wxweb\fueling::start',
    'fuelingStop' => '\zovye\api\wxweb\fueling::stop',
    'fuelingStatus' => '\zovye\api\wxweb\fueling::status',
    'fuelingPay' => '\zovye\api\wxweb\fueling::payForFueling',
    'fuelingOrderDetail' => '\zovye\api\wxweb\fueling::orderDetail',
    'fuelingOrderList' => '\zovye\api\wxweb\fueling::orderList',
    'fuelingDeviceDetail' => '\zovye\api\wxweb\fueling::deviceDetail',
]);