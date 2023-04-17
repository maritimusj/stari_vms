<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$user = Util::getCurrentUser();
if (empty($user)) {
    Util::resultAlert('请在微信中打开，谢谢！', 'error');
}

$op = Request::op('default');
if ($op == 'default') {
    $tpl_data = [
        'user' => $user->profile(false),
    ];
    
    $api_url = Util::murl('promoter');
    $jquery_url = JS_JQUERY_URL;
    $vuejs_url = JS_VUE_URL;
    $js_sdk = Util::fetchJSSDK();
    
    $tpl_data['js']['code'] = <<<JSCODE
    <script src="$jquery_url"></script>
    <script src="$vuejs_url"></script>
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
        }
        zovye_fn.reg = function(code) {
            return $.getJSON('$api_url', {op: 'reg', 'code': code});
        };
    </script>
JSCODE;
    
    app()->showTemplate('promoter/reg', ['tpl' => $tpl_data]);

} elseif ($op == 'reg') {

    $code = Request::trim('code');
    if (empty($code)) {
        JSON::fail('输入的推荐码无效，请重新输入！');
    }

    $ref = Referral::from($code);
    if (empty($ref)) {
        JSON::fail('输入的推荐码无效，请重新输入！');
    }

    
}