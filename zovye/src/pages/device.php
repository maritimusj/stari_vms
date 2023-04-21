<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTimeImmutable;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

$params = Util::getTemplateVar();

$tpl = is_array($params) ? $params : [];
$tpl['slides'] = [];

/** @var deviceModelObj $device */
$device = $tpl['device']['_obj'];

/** @var userModelObj $user */
$user = $tpl['user']['_obj'];

if (App::isAliUser()) {
    $tpl['accounts'] = [];
} else {
    if (Helper::needsTplAccountsData($device)) {
        $last_account = $user->getLastActiveAccount();
        if ($last_account) {
            $tpl['accounts'] = [$last_account];
        } else {
            $tpl['accounts'] = Account::getAvailableList($device, $user, [
                'exclude' => $params['exclude'],
                //type 不包含 Account::WXAPP，兼容以前不支持该类型的皮肤，新皮肤使用js api接口获取
                'type' => [
                    Account::NORMAL,
                    Account::VIDEO,
                    Account::AUTH,
                    Account::QUESTIONNAIRE,
                ],
                'include' => [Account::COMMISSION],
            ]);
        }
    }
}

//如果设置必须关注公众号以后才能购买商品
$goods_list_FN = false;
if (Helper::MustFollowAccount($device)) {
    if ($tpl['from'] != 'account') {
        if (empty($tpl['accounts'])) {
            $account = Account::getUserNext($device, $user);
            if ($account) {
                $tpl['accounts'][] = $account;
            }
        }
    } else {
        $goods_list_FN = true;
        $tpl = array_merge($tpl, ['goods' => $device->getGoodsList($user, [Goods::AllowPay])]);
    }
} else {
    $goods_list_FN = true;
    $tpl = array_merge($tpl, ['goods' => $device->getGoodsList($user, [Goods::AllowPay])]);
}

//如果没有货道，或只有一个货道，并且商品数量不足，或所有商品都没有允许免费领取，则无法免费领取
$lanesNum = $device->getCargoLanesNum();
if ($lanesNum == 1) {
    $goods = $device->getGoodsByLane(0);
    if (empty($goods) || $goods['num'] < 1) {
        $tpl['accounts'] = [];
    }
} elseif ($lanesNum > 1) {
    $free_goods_list = $device->getGoodsList($user, [Goods::AllowFree]);
    if (empty($free_goods_list)) {
        $tpl['accounts'] = [];
    }
} else {
    $tpl['accounts'] = [];
}

foreach ((array)$tpl['accounts'] as $index => $account) {
    //检查直接转跳的吸粉广告或公众号
    if (!empty($account['redirect_url'])) {
        //链接转跳前，先判断设备是否在线
        if ($device->isMcbOnline()) {
            Util::redirect($account['redirect_url']);
            exit('正在转跳...');
        }
        unset($tpl['accounts'][$index]);
    }
}

//广告列表
$tpl['slides'] = Advertising::getDeviceSliders($device);

$device_api_url = Util::murl('device', ['id' => $device->getId()]);
$adv_api_url = Util::murl('adv', ['deviceid' => $device->getImei()]);
$user_home_page = Util::murl('bonus', ['op' => 'home']);
$feedback_url = Util::murl('order', ['op' => 'feedback']);
$account_url = Util::murl('account');
$order_jump_url = Util::murl('order', ['op' => 'jump']);

$agent = $device->getAgent();
$mobile = '';
if ($agent) {
    $mobile = $agent->getMobile();
}

$device_name = $device->getName();
$device_imei = $device->getImei();

$pay_js = Pay::getPayJs($device, $user);
if (is_error($pay_js)) {
    Util::resultAlert($pay_js['message'], 'error');
}

$requestID = REQUEST_ID;
$tpl['js']['code'] = $pay_js;
$tpl['js']['code'] .= <<<JSCODE
<script>
const adv_api_url = "$adv_api_url";
const account_api_url = "$account_url";
const device_api_url = "$device_api_url";

if (typeof zovye_fn === 'undefined') {
    zovye_fn = {};
}
zovye_fn.closeWindow = function () {
    wx && wx.ready(function() {
        wx.closeWindow();
    })
}
zovye_fn.getAdvs = function(typeid, num, cb) {
    const params = {num};
    if (typeof typeid == 'number') {
        params['typeid'] = typeid;
    } else {
        params['type'] = typeid;
    }
    $.get(adv_api_url, params).then(function(res){
        if (res && res.status) {
            if (typeof cb === 'function') {
                cb(res.data);
            } else {
                console.log(res.data);
            }
        }
    })           
}
zovye_fn.play = function(uid, seconds, cb) {
    $.get(account_api_url, {op:'play', uid, seconds, device:'$device_imei', serial: '$requestID'}).then(function(res){
        if (cb) cb(res);
    })    
}
zovye_fn.redirectToUserPage = function() {
    window.location.href= "$user_home_page";
}
zovye_fn.redirectToFeedBack = function() {
    window.location.href= "$feedback_url&mobile=$mobile&device_name=$device_name&device_imei=$device_imei";
}
zovye_fn.getDetail = function (cb) {
    $.get(device_api_url, {op: 'detail'}).then(function (res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    })
}
zovye_fn.getAccounts = function(type, cb, max = 0) {
    type = (type || []).length === 0 ? 'all' : type;
    $.get(account_api_url, {op:'get_list', deviceId:'$device_imei', type: type, s_type: 'all', commission: true, max}).then(function(res){
        if (cb) cb(res);
    })
}
zovye_fn.redirectToAccountGetPage = function(uid) {
    $.get(account_api_url, {op:'get_url', uid, device:'$device_imei'}).then(function(res){
       if (res) {
           if (res.status && res.data.redirect) {
               window.location.href = res.data.redirect;
           } else {
               if (res.data && res.data.message) {
                   alert(res.data.message);
               }
           }
       } else {
           alert('请求转跳网址失败！');
       }
    })
}
zovye_fn.redirectToOrderPage = function() {
    window.location.href = "$order_jump_url";
}
zovye_fn.redirectToUserPage = function() {
    window.location.href = "$user_home_page";
}
JSCODE;
if ($goods_list_FN) {
    $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.getGoodsList = function(cb, type = 'pay') {
    $.get("$device_api_url", {op: 'goods', type}).then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
zovye_fn.getBalanceGoodsList = function(cb) {
    $.get("$device_api_url", {op: 'goods', type:'exchange'}).then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
zovye_fn.chooseGoods = function(goods, num, cb) {
    $.get("$device_api_url", {op: 'choose_goods', goods, num}).then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}

JSCODE;
}
if (App::isDonatePayEnabled()) {
    $donate_url = Util::murl('donate', ['device' => $device->getShadowId()]);
    $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.getDonationInfo = function(cb) {
    $.get("$donate_url").then(function(res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    });
}
JSCODE;
}
if (empty($user->settings('fansData.sex'))) {
    $profile_url = Util::murl('util', ['op' => 'profile']);
    $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.saveUserProfile = function(data) {
    $.post("$profile_url", data);
}
JSCODE;
}
//检查用户在该设备上最近失败的免费订单
$retry = settings('order.retry', []);
if ($retry['last'] > 0) {
    $order = Order::query()->where([
        'openid' => $user->getOpenid(),
        'device_id' => $device->getId(),
        'result_code <>' => 0,
        'src' => Order::ACCOUNT,
        'createtime >' => (new DateTimeImmutable("-{$retry['last']} minute"))->getTimestamp(),
    ])->orderBy('id desc')->findOne();
    if ($order) {
        if (empty($retry['max']) || $order->getExtraData('retry.total', 0) < $retry['max']) {
            $order_retry_url = Util::murl(
                'order',
                ['op' => 'retry', 'device' => $device->getShadowId(), 'uid' => $order->getOrderNO()]
            );
            $tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.retryOrder = function (cb) {
    $.get("$order_retry_url").then(function (res) {
        if (typeof cb === 'function') {
            cb(res);
        }
    })
}
JSCODE;
        }
    }
}

if (App::isBalanceEnabled()) {
    $bonus_url = Util::murl('bonus', ['serial' => REQUEST_ID, 'device' => $device->getShadowId()]);
    $mall_url = Util::murl('mall');
    $user_data = [
        'status' => true,
        'data' => $user->profile(),
    ];
    $user_data['data']['balance'] = $user->getBalance()->total();
    $user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

    $wxapp_username = settings('agentWxapp.username', '');

    $tpl['js']['code'] .= <<<JSCODE
\r\n
zovye_fn.wxapp_username = "$wxapp_username";
zovye_fn.redirectToBonusPage = function() {
    window.location.href = "$bonus_url";
}
zovye_fn.redirectToMallPage = function() {
    window.location.href = "$mall_url";
}
zovye_fn.user = JSON.parse(`$user_json_str`);
zovye_fn.getUserInfo = function (cb) {
    if (typeof cb === 'function') {
        return cb(zovye_fn.user)
    }
    return new Promise((resolve, reject) => {
        resolve(zovye_fn.user);
    });
}
zovye_fn.balancePay = function(goods, num) {
    return $.get("$bonus_url", {op: 'exchange', device: '$device_imei', goods, num});
}
JSCODE;
}

$tpl['js']['code'] .= "\r\n</script>";

if (User::isSnapshot()) {
    $tpl['js']['code'] .= app()->snapshotJs([
        'device_imei' => $device_imei,
    ]);
}

$file = Theme::getThemeFile($device, 'device');
app()->showTemplate($file, ['tpl' => $tpl]);
