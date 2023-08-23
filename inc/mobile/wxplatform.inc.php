<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = Request::op('default');
if ($op == WxPlatform::AUTH_NOTIFY) {

    //微信授权通知
    $result = WxPlatform::handleAuthorizerNotify();
    exit($result);

} elseif ($op == WxPlatform::AUTHORIZER_EVENT) {

    //微信消息推送
    $result = WxPlatform::handleAuthorizerEvent();
    exit($result);

} elseif ($op == WxPlatform::AUTH_REDIRECT_OP) {

    $code = Request::str('auth_code');
    $expired = Request::int('expires_in');
    $agent_id = Request::int('agent');

    //查询授权信息
    $auth_data = WxPlatform::getAuthData($code);
    if (is_error($auth_data)) {
        exit($auth_data['message']);
    }

    if ($auth_data['errcode'] != 0) {
        exit($auth_data['errmsg']);
    }

    $app_id = getArray($auth_data, 'authorization_info.authorizer_appid');
    if (empty($app_id)) {
        exit('无法获取AppID');
    }

    //启用任务
    $r = Job::authAccount($agent_id, Account::makeUID($app_id));

    Log::debug('wxplatform', [
        'msg' => '授权成功！',
        'data' => [
            'auth_code' => $code,
            'expired' => $expired,
            'agentId' => $agent_id,
            'job' => $r,
            'auth_data' => $auth_data,
        ],
    ]);

    echo <<<HTML
    <style>
        .tip {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        .content {
            display: flex;
            align-items: center;
        }
        .content img {
            width: 32px;
            height: 32px;
            margin-right: 10px;
        }
    </style>
<div class="tip">
    <div class="content">
        <img src="static/img/success_icon.jpg" class="icon"> 授权成功！
    </div>
</div>
HTML;
}