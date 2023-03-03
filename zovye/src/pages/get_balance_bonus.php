<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\accountModelObj;
use zovye\model\userModelObj;

/** @var userModelObj $user */
$user = Util::getTemplateVar('user');

/** @var accountModelObj $user */
$account = Util::getTemplateVar('account');

$tpl_data = Util::getTplData([$user, $account]);

$api_url = Util::murl('account');
$jquery_url = JS_JQUERY_URL;

$user_data = [
    'status' => true,
    'data' => $user->profile(),
];
$user_data['data']['balance'] = $user->getBalance()->total();
$user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$account_data = [
    'status' => true,
    'data' => $account->profile(),
];

$account_data['data']['bonus'] = $account->getBalancePrice();
$account_json_str = json_encode($account_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$js_sdk = Util::fetchJSSDK();

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
        account: JSON.parse(`$account_json_str`),
    }
    zovye_fn.getAccountInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.account)
        }
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.account);
        });
    }
    zovye_fn.getUserInfo = function (cb) {
        if (typeof cb === 'function') {
            return cb(zovye_fn.user)
        }
        return new Promise((resolve, reject) => {
            resolve(zovye_fn.user);
        });
    }
JSCODE;

$result = Util::checkBalanceAvailable($user, $account);
if (is_error($result)) {
    $tpl_data['js']['code'] .= <<<JSCODE
        \r\nzovye_fn.isOk = function(cb) {
            const res = {
                status: false,
                data: {
                    msg: `{$result['message']}`,
                }
            }
            if (typeof cb === 'function') {
                return cb(res)
            }
            return new Promise((resolve, reject) => {
                resolve(res);
            });
        }
JSCODE;
} else {
    $tpl_data['js']['code'] .= <<<JSCODE
        \r\nzovye_fn.isOk = function(cb) {
            const res = {
                status: true,
                data: {
                }
            }
            if (typeof cb === 'function') {
                return cb(res)
            }
            return new Promise((resolve, reject) => {
                resolve(res);
            });
        };
        zovye_fn.getBonus = function() {
            return $.getJSON(zovye_fn.api_url, {op: 'get_bonus', 'account': '{$account->getUid()}'});
        };
JSCODE;
}
$tpl_data['js']['code'] .= <<<JSCODE
\r\n</script>
JSCODE;

$this->showTemplate(Theme::file('balance'), ['tpl' => $tpl_data]);