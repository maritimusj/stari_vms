<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\wxWebApi;

defined('IN_IA') or exit('Access Denied');

use zovye\api\router;
use zovye\api\wxweb\api;
use zovye\api\wxweb\charging;
use zovye\api\wxweb\fueling;
use zovye\api\wxweb\member;
use zovye\api\wxweb\user;
use zovye\api\wxx\common;
use zovye\Request;

Request::extraAjaxJsonData();

$op = Request::op('default');

router::exec($op, [
    'login' => [api::class, 'login'],
    'nearBy' => [api::class, 'nearBy'],
    'advs' => [api::class, 'ads'],
    'accounts' => [api::class, 'accounts'],
    'goods' => [api::class, 'goods'],
    'get' => [api::class, 'get'],
    'exchange' => [api::class, 'exchange'],
    'pay' => [api::class, 'pay'],
    'orderStatus' => [api::class, 'orderStatus'],
    'userInfo' => [api::class, 'userInfo'],
    'getJumpUserInfo' => [api::class, 'getJumpUserInfo'],
    'getUserBank' => [api::class, 'getUserBank'],
    'setUserBank' => [api::class, 'setUserBank'],
    'getUserQRcode' => [api::class, 'getUserQRCode'],
    'updateUserQRcode' => [api::class, 'updateUserQRCode'],
    'feedback' => [api::class, 'feedback'],
    'signIn' => [api::class, 'signIn'],
    'bonus' => [api::class, 'bonus'],
    'rewardQuota' => [api::class, 'rewardQuota'],
    'reward' => [api::class, 'reward'],
    'balanceLog' => [api::class, 'balanceLog'],
    'orderList' => [api::class, 'orderList'],
    'task' => [api::class, 'task'],
    'detail' => [api::class, 'detail'],
    'submit' => [api::class, 'submit'],
    'upload' => [common::class, 'FBPic'],
    'recipient' => [api::class, 'getRecipient'],
    'updateRecipient' => [api::class, 'updateRecipient'],
    'mallOrderList' => [api::class, 'getMallOrderList'],
    'mallGoodsList' => [api::class, 'getMallGoodsList'],
    'createMallOrder' => [api::class, 'createMallOrder'],
    'pageInfo' => [common::class, 'pageInfo'],
    'rewardOrderData' => [api::class, 'rewardOrderData'],
    'validateLocation' => [api::class, 'validateLocation'],
    'chargingUserInfo' => [charging::class, 'chargingUserInfo'],
    'chargingGroupList' => [charging::class, 'groupList'],
    'chargingGroupDetail' => [charging::class, 'groupDetail'],
    'chargingDeviceList' => [charging::class, 'deviceList'],
    'chargingDeviceDetail' => [charging::class, 'deviceDetail'],
    'chargingStart' => [charging::class, 'start'],
    'chargingStop' => [charging::class, 'stop'],
    'chargingOrderStatus' => [charging::class, 'orderStatus'],
    'chargingOrderList' => [charging::class, 'orderList'],
    'chargingOrderDetail' => [charging::class, 'orderDetail'],
    'chargingStatus' => [charging::class, 'status'],
    'payForCharging' => [charging::class, 'payForCharging'],
    'chargingPayResult' => [charging::class, 'chargingPayResult'],
    'withdraw' => [charging::class, 'withdraw'],
    'payForRecharge' => [user::class, 'payForRecharge'],
    'rechargeResult' => [user::class, 'rechargeResult'],
    'rechargeList' => [user::class, 'rechargeList'],
    'memberList' => [member::class, 'getMemberList'],
    'memberUserInfo' => [member::class, 'memberUserInfo'],
    'createMember' => [member::class, 'createMember'],
    'editMember' => [member::class, 'editMember'],
    'removeMember' => [member::class, 'removeMember'],
    'transfer' => [member::class, 'transfer'],
    'memberOrderList' => [member::class, 'orderList'],
    'memberOrderDetail' => [member::class, 'orderDetail'],
    'memberChargingList' => [member::class, 'chargingList'],
    'fuelingRechargeInfo' => [fueling::class, 'rechargeInfo'],
    'fuelingStart' => [fueling::class, 'start'],
    'fuelingStop' => [fueling::class, 'stop'],
    'fuelingStatus' => [fueling::class, 'status'],
    'fuelingPay' => [fueling::class, 'payForFueling'],
    'fuelingOrderDetail' => [fueling::class, 'orderDetail'],
    'fuelingOrderList' => [fueling::class, 'orderList'],
    'fuelingDeviceDetail' => [fueling::class, 'deviceDetail'],
]);