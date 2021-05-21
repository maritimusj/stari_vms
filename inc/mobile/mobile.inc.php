<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//用户参数
$params = [
    'from' => [
        'src' => 'mobile',
        'ip' => CLIENT_IP,
        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
    'create' => true,
    'update' => true,
];

$user = Util::getCurrentUser($params);
if (empty($user)) {
    Util::resultAlert('只能从微信中打开，谢谢！', 'error');
}

$op = request::op('default');

if ($op == 'save') {

    $mobile = request::str('mobile');
    if (empty($mobile) || !preg_match(REGULAR_MOBILE, $mobile)) {
        JSON::fail('请输入正确的手机号码！');
    }

    if ($user->isAgent()) {
        JSON::fail('您已经是我们的代理商！');
    }

    if ($user->isPartner()) {
        JSON::fail('您已经是我们代理商的合伙人！');
    }

    if ($user->isKeeper()) {
        JSON::fail('您已经是我们代理商的运营人员！');
    }

    $res = User::findOne(['id <>' => $user->getId(), 'mobile' => $mobile]);
    if (!empty($res)) {
        JSON::fail('手机号码已经被其它用户使用！');
    }

    $user->setMobile($mobile);
    if (!$user->save()) {
        JSON::fail('保存手机号码失败！');
    }

    if (App::agentRegMode() == Agent::REG_MODE_AUTO) {
        $level = App::agentDefaultLevel();
        $agent_data = [
            'name' => $user->getName(),
            'company' => '<未登记>',
            'license' => '',
            'level' => $level,
            'area' => [],
            'notice' => [
                'agentApp' => 1,
                'remainWarning' => 1,
                'deviceError' => 1,
                'reviewResult' => 1,
                'agentMsg' => 1,
                'deviceOnline' => 1,
            ],
            'funcs' => App::agentDefaultFuncs(),
            'superior' => [],
            'location' => [
                'validate' => [
                    'enabled' => App::isLocationValidateEnabled() ? 1 : 0,
                    'distance' => App::userLocationValidateDistance(),
                ],
            ],
        ];

        if (App::isCommissionEnabled()) {

            $agent_data['commission'] = [
                'enabled' => 1,
                'fee_type' => App::agentDefaultCommissionFeeType(),
                'fee' => App::agentDefaultCommissionFee(),
            ];

            //佣金分享
            if (App::isAgentGSPEnabled()) {
                $agent_data['gsp'] = [
                    'enabled' => 1,
                    'mode' => 'rel',
                    'rel' => App::agentDefaultGSP(),
                    'mode_type' => App::agentDefaultGSDModeType(),
                ];
            }

            //佣金奖励
            if (App::isAgentBonusEnabled()) {
                $agent_data['bonus'] = App::agentDefaultBonus();
            }
        }

        $code = request::str('code');
        if (App::isAgentReferralEnabled()) {
            if (empty($code)) {
                JSON::fail('请填写推荐码！');
            }
        }
        if (!empty($code)) {
            $superior = Referral::getAgent($code);
            if (empty($superior)) {
                JSON::fail('推荐码不正确！');
            }
            $agent_data['superior'] = [
                'openid' => $superior->getOpenid(),
                'name' => $superior->getName(),
            ];
            $user->setSuperiorId($superior->getId());
        }

        $user->updateSettings('agentData', $agent_data);
        $user->setAgent($level);

        if ($user->save()) {
            //使用控制中心推送通知
            Job::newAgent($user->getId());
            JSON::success('恭喜您已经成功注册为代理商！');
        }
    }

    JSON::success('手机号码保存成功,请联系客服为您授权！');

} else {

    if ($op == 'check') {
        if (App::isAgentReferralEnabled()) {

            $code = request::trim('code');
            if (!empty($code)) {
                $superior = Referral::getAgent($code);
                if ($superior) {
                    JSON::success();
                }
            }

            JSON::fail('找不到推荐人，请确认推荐码是否正确！');
        }

        JSON::success();
    }
}

app()->mobilePage(
    [
        'user' =>
            [
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
                'mobile' => request::has('mobile') ? request::str('mobile') : $user->getMobile(),
            ],
    ]
);
