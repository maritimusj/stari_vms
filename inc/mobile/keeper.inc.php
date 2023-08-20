<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//用户参数
$params = [
    'create' => true,
    'update' => true,
    'from' => [
        'src' => 'mobile',
        'ip' => CLIENT_IP,
        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
];

$user = Session::getCurrentUser($params);
if (empty($user)) {
    Response::alert('只能从微信中打开，谢谢！', 'error');
}

if (Request::has('openid')) {
    $u = User::get(Request::str('openid'), true);
    //如果h5用户已经登记手机号码并且与小程序用户手机号码不同，则清除小程序用户绑定的手机号码，触发小程序端手机号码授权登录，从而重新获取用户手机号码
    //解决小程序用户已经保存了不正确手机号码导致用户无法登录的情况
    if ($u && !empty($u->getMobile()) && !empty($user->getMobile()) && $u->getMobile() != $user->getMobile()) {
        $u->setMobile('');
        $u->save();
    }
}

$op = Request::op('default');

if ($op == 'save') {

    $mobile = trim(Request::str('mobile'));
    if (empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
        JSON::fail('请输入正确的手机号码！');
    }

    if ($user->isAgent()) {
        JSON::fail('您已经是我们的代理商！');
    }

    if ($user->isPartner()) {
        JSON::fail('您已经是我们代理商的合伙人！');
    }

    $res = User::findOne([
        'id <>' => $user->getId(),
        'mobile' => $mobile,
        'app' => User::WX,
    ]);
    if (!empty($res)) {
        JSON::fail('手机号码已经被其它用户使用！');
    }

    $user->setMobile($mobile);
    if (!$user->save()) {
        JSON::fail('保存手机号码失败！');
    }

    if (!Keeper::findOne(['mobile' => $mobile])) {
        JSON::fail('对不起，您不是我们的运营人员，无法注册！');
    }

    if (!$user->setKeeper()) {
        JSON::fail('手机绑定失败！');
    }

    if ($user->save()) {
        JSON::success('手机绑定成功！');
    }
}

Response::keeperPage([
        'user' =>
            [
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
                'mobile' => $user->getMobile(),
            ],
    ]
);
