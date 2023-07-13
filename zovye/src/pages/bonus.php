<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

/** @var deviceModelObj $device */
$device = Util::getTemplateVar('device');

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

$tpl_data = Util::getTplData([$user]);

$user_data = [
    'status' => true,
    'data' => $user->profile(),
];
$user_data['data']['balance'] = $user->getBalance()->total();
$user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$api_url = Util::murl('bonus');
$account_url = Util::murl('account');
$adv_api_url = Util::murl('adv');
$user_home_page = Util::murl('bonus', ['op' => 'home']);
$task_page = Util::murl('task', ['serial' => REQUEST_ID, 'device' => $device ? $device->getShadowId() : '']);
$mall_url = Util::murl('mall');

$jquery_url = JS_JQUERY_URL;

$js_sdk = Session::fetchJSSDK();
$wxapp_username = settings('agentWxapp.username', '');

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
    wx.ready(function(){
        wx.hideAllNonBaseMenuItem();
        wx.showMenuItems({
            menuList: [
                "menuItem:share:appMessage",
                "menuItem:share:timeline",
                "menuItem:favorite",
                "menuItem:copyUrl",
            ] // 要显示的菜单项，所有menu项见附录3
        });
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
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.user);
        });
    }
    zovye_fn.getAdvs = function(typeid, num, cb) {
        const params = {num};
        if (typeof typeid == 'number') {
            params['typeid'] = typeid;
        } else {
            params['type'] = typeid;
        }
        $.get("$adv_api_url", params).then(function(res){
            if (res && res.status) {
                if (typeof cb === 'function') {
                    cb(res.data);
                } else {
                    console.log(res.data);
                }
            }
        })           
    }
    zovye_fn.getAccounts = function(type, max) {
        return $.getJSON(zovye_fn.api_url, {op: 'account', type, max});
    }
    zovye_fn.play = function(uid, seconds, cb) {
        $.get("$account_url", {op: 'play', uid, seconds}).then(function(res){
            if (cb) cb(res);
        })
    }
    zovye_fn.getBonus = function(uid) {
        return $.getJSON('$account_url', {op: 'get_bonus', 'account': uid});
    };
    zovye_fn.redirectToUserPage = function() {
        window.location.replace("$user_home_page");
    }
    zovye_fn.redirectToTaskPage = function() {
        window.location.href = "$task_page";
    }
    zovye_fn.redirectToMallPage = function() {
        window.location.replace("$mall_url");
    }
JSCODE;

if (!$user->isSigned()) {
    $tpl_data['js']['code'] .= <<<JSCODE
    \r\nzovye_fn.signIn = function() {
        return $.getJSON(zovye_fn.api_url, {op: 'signIn'});
    }    
JSCODE;
}

$tpl_data['js']['code'] .= <<<JSCODE
\r\n</script>
JSCODE;

$filename = Theme::getThemeFile($device, 'bonus');
app()->showTemplate($filename, ['tpl' => $tpl_data]);