<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

$tpl_data = TemplateUtil::getTplData([$user]);

$user_data = [
    'status' => true,
    'data' => $user->profile(),
];

$user_data['data']['balance'] = $user->getBalance()->total();
$user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$api_url = Util::murl('bonus');
$mall_url = Util::murl('mall');
$mall_order_url = Util::murl('mall', ['op' => 'order']);
$balance_logs_url = Util::murl('bonus', ['op' => 'logsPage']);
$order_jump_url = Util::murl('order', ['op' => 'jump']);
$jquery_url = JS_JQUERY_URL;

$wxapp_username = settings('agentWxapp.username', '');

$js_sdk = Util::jssdk();

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
    });

    const zovye_fn = {
        api_url: "$api_url",
        user: JSON.parse(`$user_json_str`),
        wxapp_username: "$wxapp_username",
    }
    zovye_fn.getUserInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.user)
        }
        return new Promise((resolve) => {
            resolve(zovye_fn.user);
        });
    }
    zovye_fn.redirectToBalanceLogPage = function() {
        window.location.href = "$balance_logs_url";
    }
    zovye_fn.redirectToOrderPage = function() {
        window.location.href = "$order_jump_url";
    }    
    zovye_fn.redirectToBonusPage = function() {
        window.location.replace("$api_url");
    }
    zovye_fn.redirectToMallPage = function() {
        window.location.replace("$mall_url");
    }
    zovye_fn.redirectToMallOrderPage = function() {
        window.location.href = "$mall_order_url";
    }
\r\n
JSCODE;

if (App::isFlashEggEnabled()) {
    $flash_egg_url = Util::murl('account', ['op' => 'flash_egg']);
    $tpl_data['js']['code'] .= <<<JSCODE
    zovye_fn.redirectToFlashEggPage = function() {
        window.location.href = "$flash_egg_url";
    }
\r\n
JSCODE;
}

$tpl_data['js']['code'] .= "\r\n</script>";
Response::showTemplate('user', ['tpl' => $tpl_data], true);