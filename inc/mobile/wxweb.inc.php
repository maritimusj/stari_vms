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
    'advs' => '\zovye\api\wxweb\api::advs',
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
]);