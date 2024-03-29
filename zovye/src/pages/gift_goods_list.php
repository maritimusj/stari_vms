<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\util\PayUtil;
use zovye\util\TemplateUtil;
use zovye\util\Util;

/** @var deviceModelObj $device */
$device = TemplateUtil::getTemplateVar('device');

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

$api_url = Util::murl('sample');
$account_api_url = Util::murl('account');
$feedback_url = Util::murl('order', ['op' => 'feedback']);
$order_jump_url = Util::murl('order', ['op' => 'jump']);
$user_home_page = Util::murl('bonus', ['op' => 'home']);

$agent = $device->getAgent();
$mobile = '';
if ($agent) {
    $mobile = $agent->getMobile();
}

$device_name = $device->getName();
$device_imei = $device->getImei();

$tpl_data = TemplateUtil::getTplData();

$tpl_data['user'] = $user->profile();
$tpl_data['device'] = $device->profile();

$tpl_data['timeout'] = App::getDeviceWaitTimeout();

$pay_js = Pay::getPayJs($device, $user);
if (is_error($pay_js)) {
    $pay_js = PayUtil::getDummyPayJs();
}

$tpl_data['js']['code'] = $pay_js;

$requestID = REQUEST_ID;

$tpl_data['js']['code'] .= <<<JSCODE
<script>
    zovye_fn.getGoodsList = function(fn) {
        $.getJSON("$api_url", {op: 'goods'}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getFreeGoodsList = function(fn) {
        $.getJSON("$api_url", {op: 'goods', 'free': true}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getPayGoodsList = function(fn) {
        $.getJSON("$api_url", {op: 'goods', 'pay': true}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.getGoodsDetail = function(id, fn) {
        $.getJSON("$api_url", {op: 'detail', id: id, device: "{$device->getImei()}"}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.play = function(uid, seconds, fn) {
        $.getJSON("$account_api_url", {op: 'play', uid, seconds, device: "{$device->getImei()}", serial: "$requestID"}).then(function(res){
            if (typeof fn === 'function') {
                fn(res);
            }
        })
    }
    zovye_fn.redirectToFeedBack = function() {
        window.location.href= "$feedback_url&mobile=$mobile&device_name=$device_name&device_imei=$device_imei";
    }
    zovye_fn.redirectToOrderPage = function() {
        window.location.href = "$order_jump_url";
    }
    zovye_fn.redirectToUserPage = function() {
        window.location.href = "$user_home_page";
    }
\r\n
JSCODE;

//闪蛋活动转跳
if (App::isFlashEggEnabled()) {
    $flash_gift_url = Util::murl('account', ['op' => 'gift', 'device' => $device->getImei()]);
    $tpl_data['js']['code'] .= <<<JSCODE
    zovye_fn.redirectToGiftPage = function() {
        window.location.href= "$flash_gift_url";
    }
JSCODE;
}

$tpl_data['js']['code'] .= "\r\n</script>";

$filename = Theme::getThemeFile($device, 'device');
Response::showTemplate($filename, ['tpl' => $tpl_data]);