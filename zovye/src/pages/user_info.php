<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

/** @var deviceModelObj $device */
$device = Util::getTemplateVar('device');

$tpl_data = Util::getTplData([$user, $device]);

$user_data = [
    'status' => true,
    'data' => $user->profile(),
];

$user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$api_url = Util::murl('util', ['op' => 'profile', 'device' => $device->getImei()]);
$jquery_url = JS_JQUERY_URL;

$js_sdk = Session::fetchJSSDK();

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
    }
    zovye_fn.getUserInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.user)
        }
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.user);
        });
    }
    zovye_fn.update = function(info) {
        return $.getJSON(zovye_fn.api_url, info);
    }
</script>
JSCODE;

app()->showTemplate(Theme::file('user_info'), ['tpl' => $tpl_data]);