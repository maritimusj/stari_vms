<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;

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

$op = Request::op('default');

if ($op == 'save') {

    $result = DBUtil::transactionDo(function () use ($user) {
        $mobile = Request::str('mobile');
        if (empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
            throw new RuntimeException('请输入正确的手机号码！');
        }

        if ($user->isAgent()) {
            throw new RuntimeException('您已经是我们的代理商！');
        }

        if ($user->isPartner()) {
            throw new RuntimeException('您已经是我们代理商的合伙人！');
        }

        if ($user->isKeeper()) {
            throw new RuntimeException('您已经是我们代理商的运营人员！');
        }

        $res = User::findOne([
            'id <>' => $user->getId(),
            'mobile' => $mobile,
            'app' => User::WX,
        ]);

        if (!empty($res)) {
            throw new RuntimeException('手机号码已经被其它用户使用！');
        }

        $user->setMobile($mobile);
        if (!$user->save()) {
            throw new RuntimeException('保存手机号码失败！');
        }

        if (App::getAgentRegMode() == Agent::REG_MODE_AUTO) {
            $level = App::getAgentDefaultLevel();
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
                'funcs' => App::getAgentDefaultFuncs(),
                'superior' => [],
                'location' => [
                    'validate' => [
                        'enabled' => App::isLocationValidateEnabled() ? 1 : 0,
                        'distance' => App::getUserLocationValidateDistance(),
                    ],
                ],
            ];

            if (App::isCommissionEnabled()) {

                $agent_data['commission'] = [
                    'enabled' => 1,
                    'fee_type' => App::getAgentDefaultCommissionFeeType(),
                    'fee' => App::getAgentDefaultCommissionFee(),
                ];

                //佣金分享
                if (App::isAgentGSPEnabled()) {
                    $gsp = App::getAgentDefaultGSP();
                    $agent_data['gsp'] = [
                        'enabled' => 1,
                        'mode' => 'rel',
                        'rel' => [
                            'level0' => $gsp['level0'],
                            'level1' => $gsp['level1'],
                            'level2' => $gsp['level2'],
                            'level3' => $gsp['level3'],
                        ],
                        'order' => $gsp['order'] ?? [],
                        'mode_type' => App::getAgentDefaultGSDModeType(),
                    ];
                }

                //佣金奖励
                if (App::isAgentBonusEnabled()) {
                    $agent_data['bonus'] = App::getAgentDefaultBonus();
                }
            }

            $code = Request::str('code');
            if (App::isAgentReferralEnabled()) {
                if (empty($code)) {
                    throw new RuntimeException('请填写推荐码！');
                }
            }
            if (!empty($code)) {
                $superior = Referral::getAgent($code);
                if (empty($superior)) {
                    throw new RuntimeException('推荐码不正确！');
                }
                $agent_data['superior'] = [
                    'openid' => $superior->getOpenid(),
                    'name' => $superior->getName(),
                ];
                $user->setSuperiorId($superior->getId());
            }

            if (!$user->updateSettings('agentData', $agent_data)) {
                throw new RuntimeException('失败01，请联系管理员！');
            }

            if (!$user->setAgent($level)) {
                throw new RuntimeException('失败02，请联系管理员！');
            }

            if ($user->save()) {
                //使用控制中心推送通知
                Job::newAgent($user->getId());

                return ['message' => '恭喜您已经成功注册为代理商！'];
            }
        }

        return ['message' => '手机号码保存成功,请联系客服为您授权！'];
    });

    if (is_error($result)) {
        JSON::fail($result['message']);
    }

    JSON::success(empty($result['message']) ? '失败！' : $result['message']);

} else {

    if ($op == 'check') {
        if (App::isAgentReferralEnabled()) {

            $code = Request::trim('code');
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

app()->mobilePage([
        'user' =>
            [
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
                'mobile' => Request::has('mobile') ? Request::str('mobile') : $user->getMobile(),
            ],
    ]
);
