<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\userModelObj;

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

$tpl_data = Util::getTplData([$user]);

$user_data = [
    'status' => true,
    'data' => $user->profile(),
];

$user_data['data']['balance'] = $user->getBalance()->total();
$user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$api_url = Util::murl('mall');
$user_home_page = Util::murl('bonus', ['op' => 'home']);
$bonus_url = Util::murl('bonus');
$order_page = Util::murl('mall', ['op' => 'order']);
$adv_api_url = Util::murl('adv');

$jquery_url = JS_JQUERY_URL;

$js_sdk = Util::fetchJSSDK();

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
    zovye_fn.createOrder = function(goods, num) {
        return $.getJSON(zovye_fn.api_url, {op: 'create_order', goods, num});
    }
    zovye_fn.getGoodsList = function(page, pagesize) {
        return $.getJSON(zovye_fn.api_url, {op: 'goods_list', page, pagesize});
    }
    zovye_fn.getRecipient = function() {
        return $.getJSON(zovye_fn.api_url, {op: 'recipient'});
    }
    zovye_fn.updateRecipient = function(name, phoneNum, address) {
        return $.getJSON(zovye_fn.api_url, {op: 'update_recipient', name, phoneNum, address});
    }
    zovye_fn.redirectToBonusPage = function() {
        window.location.replace("$bonus_url");
    }
    zovye_fn.redirectToUserPage = function() {
        window.location.replace("$user_home_page");
    }
    zovye_fn.redirectToOrderPage = function() {
        window.location.href = "$order_page";
    }    
</script>
JSCODE;

app()->showTemplate(Theme::file('mall'), ['tpl' => $tpl_data]);