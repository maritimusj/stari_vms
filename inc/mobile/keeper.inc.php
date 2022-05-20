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

$user = Util::getCurrentUser($params);
if (empty($user)) {
    Util::resultAlert('只能从微信中打开，谢谢！', 'error');
}

$op = request::op('default');

if ($op == 'save') {

    $mobile = trim(request::str('mobile'));
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
        JSON::fail('对不起，您不是我们的营运人员，无法注册！');
    }

    if (!$user->setKeeper()) {
        JSON::fail('手机绑定失败！');
    }

    if ($user->save()) {
        JSON::success('手机绑定成功！');
    }
}

app()->keeperPage(
    [
        'user' =>
            [
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
                'mobile' => $user->getMobile(),
            ],
    ]
);
