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

$task_api_url = Util::murl('task');
$account_api_url = Util::murl('account');
$adv_api_url = Util::murl('adv');
$user_home_page = Util::murl('bonus', ['op' => 'home']);
$upload_api_url = Util::murl('util', ['op' => 'upload_pic']);

$jquery_url = JS_JQUERY_URL;
$axios_url = JS_AXIOS_URL;

$js_sdk = Util::fetchJSSDK();
$wxapp_username = settings('agentWxapp.username', '');

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
<script src="$axios_url"></script>
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
        api_url: "$task_api_url",
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
    zovye_fn.getTask = function(max) {
        return $.getJSON(zovye_fn.api_url, {op: 'get_list', max});
    }
    zovye_fn.getAccounts = function(max) {
        return $.getJSON("$account_api_url", {op: 'get_list', type: 40, max, balance: true});
    }    
    zovye_fn.getDetail = function(uid) {
        return $.getJSON(zovye_fn.api_url, {op: 'detail', uid});
    }
    zovye_fn.upload = function(data) {
        const param = new FormData();
        param.append('pic', data);
        const config = {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        }
        return new Promise((resolve, reject) => {
             axios.post("$upload_api_url", param, config).then((res) => {
                return res.data;
             }).then((res) => {
                 if (res.status && res.data) {
                     resolve(res.data.data);
                 } else {
                    reject(res.msg || '上传失败！');
                 }
             }).catch(() => {
               reject("上传失败！");
             });
        })
    }
    zovye_fn.submit = function(uid, data, cb) {
        $.post(zovye_fn.api_url, {op: 'submit', uid, data}).then(function(res){
            if (cb) cb(res);
        })
    }
    zovye_fn.redirectToUserPage = function() {
        window.location.replace("$user_home_page");
    }
JSCODE;

$tpl_data['js']['code'] .= <<<JSCODE
\r\n</script>
JSCODE;

$filename = Theme::getThemeFile($device, 'task');
app()->showTemplate($filename, ['tpl' => $tpl_data]);