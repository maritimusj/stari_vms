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

$api_url = Util::murl('bonus');
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
        return new Promise((resolve) => {
            resolve(zovye_fn.user);
        });
    }
    zovye_fn.getBalanceLog = function(lastId, pagesize) {
        return $.getJSON(zovye_fn.api_url, {op: 'logs', lastId, pagesize});
    }
</script>
JSCODE;

Response::showTemplate('balance_log', ['tpl' => $tpl_data], true);