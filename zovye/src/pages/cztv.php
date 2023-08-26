<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

/**
 * 暖心小屋定制页面
 * @param deviceModelObj|null $device
 * @param userModelObj|null $user
 * @param string $redirect_url
 */

/** @var deviceModelObj $device */
$device = TemplateUtil::getTemplateVar('device');

/** @var userModelObj $user */
$user = TemplateUtil::getTemplateVar('user');

/** @var string $redirect_url */
$redirect_url = TemplateUtil::getTemplateVar('redirect_url');

$tpl = [];

if (empty($user)) {
    //尝试调起用户登录页面
    $tpl['js']['code'] = <<<JSCODE
<script>
	const u = navigator.userAgent;
    const isAndroid = u.indexOf('Android') > -1 || u.indexOf('Linux') > -1; //g
    const isIOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/); //ios终端
    if (isAndroid) {
        if (window.AndroidJsInterface && window.AndroidJsInterface.login) {
            window.AndroidJsInterface.login();
        } else {
            window.location.href = "$redirect_url";
        }
    } else {
        if (window.webkit && window.webkit.messageHandlers.login) {
            window.webkit.messageHandlers.login.postMessage(null);
        } else {
            window.location.href = "$redirect_url";
        }
    }
</script>
JSCODE;
    $file = Theme::getThemeFile($device, 'cztv');
    Response::showTemplate($file, ['tpl' => $tpl]);
}

$user_json_str = json_encode($user->profile(), JSON_HEX_TAG | JSON_HEX_QUOT);

$user_openid = $user->getOpenid();

$device_api_url = Util::murl('device', ['id' => $device->getId()]);
$order_jump_url = Util::murl('order', ['op' => 'jump', 'user' => $user_openid]);

$tpl['js']['code'] .= <<<JSCODE
<script>
    const api_url = "$device_api_url";
    zovye_fn = {
        user: JSON.parse(`$user_json_str`),
    };

    zovye_fn.redirectToOrderPage = function() {
        window.location.href = "$order_jump_url";
    }
JSCODE;
$tpl['js']['code'] .= <<<JSCODE
\r\nzovye_fn.getGoodsList = function(cb, type = 'free') {
    $.get(api_url, {op: 'goods', user: '$user_openid', type}).then(function(res) {
            if (typeof cb === 'function') {
                cb(res);
            }
    });
}
zovye_fn.get = function(goods, cb) {
    $.get(api_url, {op: 'get', goods,  user: '$user_openid'}).then(function(res) {
            if (typeof cb === 'function') {
                cb(res);
            }
    });
}

JSCODE;

$tpl['js']['code'] .= "\r\n</script>";

$file = Theme::getThemeFile($device, 'cztv');
Response::showTemplate($file, ['tpl' => $tpl]);