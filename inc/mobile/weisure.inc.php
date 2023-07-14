<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\account\WeiSureAccount;

if (Request::is_post()) {
    Log::debug('weisure', [
        'raw' => Request::raw(),
        'userAction' => Request::json('userAction', ''),
        'actionTime' => Request::json('actionTime', ''),
        'outerUserId' => Request::json('outerUserId', ''),
    ]);

    if (App::isWeiSureEnabled()) {
        WeiSureAccount::cb(Request::json());
    } else {
        Log::debug('weisure', [
            'error' => '微保没有启用！',
        ]);
    }

    exit(WeiSureAccount::ResponseOk);
}

$op = Request::op('default');
if ($op == 'check') {
    //40s后执行超时任务
    CtrlServ::scheduleDelayJob('weisure_timeout', [
        'user' => Request::trim('user'),
        'device' => Request::trim('device'),
    ], 40);

    JSON::success('Ok');
}

$user = Session::getCurrentUser([
    'create' => true,
    'update' => true,
]);

if (empty($user)) {
    Response::alert('找不到这个用户！', 'error');
}

if ($user->isBanned()) {
    if (Request::is_ajax()) {
        JSON::fail('用户暂时不可用！');
    }
    Response::alert('用户暂时不可用！');
}

$tpl_data = Util::getTplData([$user]);

$acc = Account::findOneFromType(Account::WEISURE);
if (empty($acc)) {
    Response::alert('活动暂时不可用！');
}

if (Util::checkLimit($acc, $user, [], 1)) {
    Response::alert('已经参加过活动！');
}

$config = $acc->get('config', []);
if (empty($config['companyId']) || isEmptyArray($config['h5url'])) {
    Response::alert('活动没有正确配置！');
}

$device = $user->getLastActiveDevice();
if (empty($device)) {
    Response::alert('请重新扫描设备二维码！');
}

$params = [
    'companyId' => $config['companyId'],
    'wtagid' => $config['wtagid'],
    'outerUserId' => base64_encode("{$user->getOpenid()}:{$device->getShadowId()}"),
];

$config['parsed_h5url']['query'] = http_build_query(is_array($config['parsed_h5url']['query']) ? array_merge($config['parsed_h5url']['query'], $params) : $params);

$weisure_url = Util::buildUrl($config['parsed_h5url']);
$get_url = Util::murl('entry', [
    'account' => $acc->getUid(),
]);

$user_data = [
    'status' => true,
    'data' => $user->profile(),
];

$user_json_str = json_encode($user_data, JSON_HEX_TAG | JSON_HEX_QUOT);

$js_sdk = Session::fetchJSSDK();
$jquery_url = JS_JQUERY_URL;

$api_url = Util::murl('weisure', [
    'op' => 'check', 
    'user' => $user->getOpenid(),
    'device' => $device->getImei(),
]);

$tpl_data['js']['code'] = <<<JSCODE
<script src="$jquery_url"></script>
$js_sdk
<script>
wx.ready(function(){
    wx.hideAllNonBaseMenuItem();
});

const zovye_fn = {
    user: JSON.parse(`$user_json_str`),
}

zovye_fn.getUserInfo = function (cb) {
    if (typeof cb === 'function') {
        return cb(zovye_fn.user)
    }
}

zovye_fn.redirectToWeisure = function() {
    window.location.replace("$weisure_url");
}

zovye_fn.redirectToGetPage = function() {
    $.getJSON("$api_url", res => {
        if (res && res.status) {
            window.location.replace("$get_url");
        } else {
            alert(res.data.message);
        }
    });
}
</script>
JSCODE;

Response::showTemplate('weisure', ['tpl' => $tpl_data], true);