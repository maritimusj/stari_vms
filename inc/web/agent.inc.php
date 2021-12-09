<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use DateTimeImmutable;
use Exception;
use zovye\model\agent_appModelObj;
use zovye\model\agent_msgModelObj;
use zovye\model\agentModelObj;
use zovye\model\agent_vwModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\deviceModelObj;
use zovye\model\gsp_userModelObj;
use zovye\model\keeperModelObj;
use zovye\model\msgModelObj;
use zovye\model\userModelObj;

$agent_levels = settings('agent.levels');
$commission_enabled = App::isCommissionEnabled();

$is_ajax = request::is_ajax();
$op = request::op('default');

if ($op == 'default') {

    //分配设备控件查询代理详情
    if ($is_ajax && request::has('id')) {
        $agent = Agent::get(request::int('id'));
        if ($agent) {
            $data = [
                'id' => $agent->getId(),
                'nickname' => strval($agent->getNickname()),
                'avatar' => strval($agent->getAvatar()),
                'mobile' => strval($agent->getMobile()),
                'state' => intval($agent->getState()),
                'commission_enabled' => $commission_enabled && $agent->isCommissionEnabled(),
            ];

            $data['agentData'] = [
                'area' => $agent->settings('agentData.area', []),
                'company' => $agent->settings('agentData.company', ''),
                'name' => $agent->settings('agentData.name', ''),
                'level' => $agent->settings('agentData.level'),
            ];

            JSON::success($data);
        }

        JSON::fail('没有找到这个代理商');
    }

    $tpl_data = [
        'user_state_class' => [
            0 => 'normal',
            1 => 'banned',
        ],
    ];

    $agents = [
        'total' => 0,
        'page' => 1,
        'totalpage' => 1,
        'list' => [],
    ];

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $query = Principal::agent();

    //搜索指定ID
    $ids = Util::parseIdsFromGPC();
    if (!empty($ids)) {
        $query->where(['id' => $ids]);
    }

    //搜索用户昵称或者手机号码
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'nickname LIKE' => "%$keywords%",
            'mobile LIKE' => "%$keywords%",
            'name LIKE' => "%$keywords%",
        ]);
    }

    $referral_enabled = App::isAgentReferralEnabled();
    $total = $query->count();

    if ($total > 0) {
        $total_page = ceil($total / $page_size);
        if ($page > $total_page) {
            $page = 1;
        }

        $agents['total'] = $total;
        $agents['totalpage'] = $total_page;
        $agents['page'] = $page;

        $query->page($page, $page_size);
        $query->orderBy('id desc');

        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        /** @var  agent_vwModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'openid' => $entry->getOpenid(),
                'nickname' => $entry->getNickname(),
                'avatar' => $entry->getAvatar(),
                'mobile' => $entry->getMobile(),
                'state' => $entry->getState(),
                'total' => $entry->getDeviceTotal(),
                'commission_enabled' => $commission_enabled && $entry->isCommissionEnabled(),
            ];

            if ($is_ajax) {
                $level_data = $agent_levels[$entry->settings('agentData.level')] ?: '';
                $data['agentData'] = [
                    'area' => $entry->settings('agentData.area', []),
                    'company' => $entry->settings('agentData.company', ''),
                    'name' => $entry->settings('agentData.name', ''),
                    'level' => $level_data,
                ];
            } else {
                $agent_data = $entry->get('agentData', []);

                $data['agentData'] = $agent_data;
                $data['partners'] = $agent_data['partners'] ? count($agent_data['partners']) : 0;
                $data['superior'] = $entry->getSuperior();
                $data['createtime'] = date('Y-m-d H:i:s', $entry->getCreatetime());
                $data['m'] = Stats::getMonthTotal($entry); //$entry->getMTotal('today');
                if ($data['commission_enabled']) {
                    $data['commission'] = [
                        'money' => $entry->getCommissionBalance()->total(),
                    ];
                }
            }
            if ($referral_enabled) {
                $referral = $entry->getReferral();
                if ($referral) {
                    $data['referral'] = $referral->getCode();
                }
            }
            $agents['list'][] = $data;
        }
    }

    if ($is_ajax) {
        $agents['serial'] = request::str('serial') ?: microtime(true) . '';
        JSON::success($agents);
    }

    $tpl_data['page'] = $page;
    $tpl_data['agents'] = $agents['list'];
    $tpl_data['mobile_url'] = Util::murl('mobile');
    $tpl_data['keywords'] = $keywords;

    $tpl_data['agent_levels'] = $agent_levels;

    app()->showTemplate('web/agent/default', $tpl_data);
} elseif ($op == 'agent_sub') {
    $tpl_data = [
        'user_state_class' => [
            0 => 'normal',
            1 => 'banned',
        ],
    ];

    $agents = [
        'list' => [],
    ];

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $sup_agent = Agent::get(request::int('id'));

    $agent_ids = Agent::getAllSubordinates($sup_agent);
    if (!empty($agent_ids)) {
        $query = Principal::agent(['id' => $agent_ids]);
        //搜索用户名
        $referral_enabled = App::isAgentReferralEnabled();
        $total = $query->count();

        if ($total > 0) {
            $total_page = ceil($total / $page_size);
            if ($page > $total_page) {
                $page = 1;
            }

            $query->page($page, $page_size);
            $query->orderBy('id desc');

            $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

            /** @var  agent_vwModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'openid' => $entry->getOpenid(),
                    'nickname' => strval($entry->getNickname()),
                    'avatar' => strval($entry->getAvatar()),
                    'mobile' => strval($entry->getMobile()),
                    'state' => intval($entry->getState()),
                    'total' => intval($entry->getDeviceTotal()),
                    'commission_enabled' => $commission_enabled && $entry->isCommissionEnabled(),
                ];

                $agent_data = $entry->get('agentData', []);
                $data['agentData'] = $agent_data;
                $data['partners'] = $agent_data['partners'] ? count($agent_data['partners']) : 0;
                $data['superior'] = $entry->getSuperior();
                $data['createtime'] = date('Y-m-d H:i:s', $entry->getCreatetime());
                $data['m'] = Stats::getMonthTotal($entry); //$entry->getMTotal('today');
                if ($data['commission_enabled']) {
                    $data['commission'] = [
                        'money' => $entry->getCommissionBalance()->total(),
                    ];
                }

                if ($referral_enabled) {
                    $referral = $entry->getReferral();
                    if ($referral) {
                        $data['referral'] = $referral->getCode();
                    }
                }
                $agents['list'][] = $data;
            }
        }
    }

    $withdraw_query = CommissionBalance::query(['src' => CommissionBalance::WITHDRAW]);
    $withdraw_query->where('(updatetime IS NULL OR updatetime=0)');

    $tpl_data['withdraw_num'] = $withdraw_query->count();
    $tpl_data['agents'] = $agents['list'];
    $tpl_data['mobile_url'] = Util::murl('mobile');

    $tpl_data['sup'] = $sup_agent->profile();

    app()->showTemplate('web/agent/agent_sub', $tpl_data);
} elseif (in_array($op, ['create', 'edit', 'agent_base', 'agent_funcs', 'agent_notice', 'agent_commission', 'agent_misc', 'agent_payment'])) {

    $id = request::int('id');
    $agent = null;
    if ($op == 'create') {
        $res = User::get($id);
        if ($res) {
            if ($res->isAgent() || $res->isPartner()) {
                Util::itoast('用户已经是代理商或者合伙人！', $this->createWebUrl('agent'), 'error');
            }
            $agent = $res->agent();
        }
    } else {
        $agent = Agent::get($id);
    }

    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
    }

    $agent_data = $agent->get('agentData', []);
    $superior = $agent->getSuperior();
    $superior_data = $superior ? $superior->get('agentData') : null;

    if (!isset($agent_data['location']['validate']['enabled'])) {
        $agent_data['location']['validate']['enabled'] = App::isLocationValidateEnabled();
        $agent_data['location']['validate']['distance'] = App::userLocationValidateDistance(1);
    }
    if (!isset($agent_data['gsp.order'])) {
        $agent_data['gsp.order'] = [
            'f' => 1,
            'b' => 1,
            'p' => 1,
        ];
    }

    if ($op == 'agent_commission') {
        $data = $agent->settings('agentData.gsp.users', []);
        $free_gsp_users = array_map(
            function ($openid, $entry) {
                $res = User::get($openid, true);
                if ($res) {
                    $data = [
                        'id' => $res->getId(),
                        'nickname' => $res->getNickname(),
                        'avatar' => $res->getAvatar(),
                        'percent' => $entry['percent'],
                        'createtime_formatted' => date('Y-m-d H:i:s', $entry['createtime']),
                    ];
                    if ($entry['percent']) {
                        $data['percent'] = number_format($entry['percent'] / 100, 2);
                    } else {
                        $data['amount'] = number_format($entry['amount'] / 100, 2);
                    }
                    $data['order_type'] = is_array($entry['order']) ? $entry['order'] : [
                        'f' => 1,
                        'b' => 1,
                        'p' => 1,
                    ];
                    if ($res->isGSPor()) {
                        $data['commission_balance_formatted'] = number_format($res->getCommissionBalance()->total() / 100, 2);
                    }
                    return $data;
                }
                return [];
            },
            array_keys($data),
            $data
        );

        $mixed_gsp_users = [];
        $query = GSP::query(['agent_id' => $agent->getId()]);
        /** @var gsp_userModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $user = GSP::getUser($agent, $entry);
            if ($user) {
                $uid = $entry->getUid();
                $g = [
                    'val_type' => $entry->getValType(),
                    'val' => number_format($entry->getVal() / 100, 2),
                ];
                if (empty($mixed_gsp_users[$uid])) {
                    $data = [
                        'id' => $entry->getId(),
                        'nickname' => $user->getNickname(),
                        'avatar' => $user->getAvatar(),
                    ];

                    if ($entry->isFreeOrderIncluded()) {
                        $data['f'] = $g;
                    }
                    if ($entry->isPayOrderIncluded()) {
                        $data['p'] = $g;
                    }
                    $mixed_gsp_users[$uid] = $data;
                } else {
                    if ($entry->isFreeOrderIncluded()) {
                        $mixed_gsp_users[$uid]['f'] = $g;
                    }
                    if ($entry->isPayOrderIncluded()) {
                        $mixed_gsp_users[$uid]['p'] = $g;
                    }
                }
            }
        }
    }

    $tpl_data = [
        'op' => $op,
        'agent_levels' => $agent_levels,
        'id' => $id,
        'agent' => $agent,
        'agent_data' => $agent_data,
        'superior' => $superior,
        'superior_data' => $superior_data,
        'free_gsp_users' => $free_gsp_users ?? null,
        'mixed_gsp_users' => $mixed_gsp_users ?? null,
    ];

    if ($op == 'agent_misc') {
        $tpl_data['themes'] = Theme::all();
    }

    app()->showTemplate('web/agent/edit', $tpl_data);
} elseif ($op == 'save') {

    $id = request::int('id');
    $user = User::get($id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('agent'), 'error');
    }

    if ($user->isPartner()) {
        Util::itoast('用户已经是其他代理商的合伙人！', $this->createWebUrl('agent'), 'error');
    }

    $agentEdit = $user->isAgent();

    if (request::bool('agent_base')) {

        $mobile = request::trim('mobile');
        if (empty($mobile) || !preg_match(REGULAR_MOBILE, $mobile)) {
            Util::itoast('手机号码无效！', $this->createWebUrl('agent', ['op' => request::str('from'), 'id' => $id]), 'error');
        }

        if (User::findOne(['mobile' => $mobile, 'id <>' => $user->getId()])) {
            Util::itoast('手机号码已经被其它用户使用！', $this->createWebUrl('agent', ['op' => request::str('from'), 'id' => $id]), 'error');
        }

        $name = request::trim('name');
        $company = request::trim('company');
        $license = request::trim('license');
        $level = request::trim('level');
        $area = array_intersect_key(request('area'), ['province' => '省', 'city' => '市', 'district' => '区']);

        //上级代理
        $superior_data = [];

        $openid_s = request::trim('superior');
        if ($openid_s) {
            $superior = Agent::get($openid_s, true);
            if (empty($superior) || !$superior->isAgent() || $superior->getId() == $user->getId()) {
                Util::itoast('请选择正确的上级用户！', $this->createWebUrl('agent', ['op' => request::str('from'), 'id' => $id]), 'error');
            }

            if ($superior->getId() != $user->getSuperiorId()) {
                $user->setSuperiorId($superior->getId());
                $superior_data = [
                    'openid' => $superior->getOpenid(),
                    'name' => $superior->getNickname(),
                ];
            }
        } else {
            $user->setSuperiorId(null);
        }

        if ($user->isAgent()) {

            $user->updateSettings('agentData.name', $name);
            $user->updateSettings('agentData.company', $company);
            $user->updateSettings('agentData.license', $license);
            $user->updateSettings('agentData.level', $level);
            $user->updateSettings('agentData.area', $area);
            $user->updateSettings('agentData.superior', $superior_data);
        } else {

            $agent_data = [
                'name' => $name,
                'company' => $company,
                'license' => $license,
                'level' => $level,
                'area' => $area,
                'notice' => [
                    'agentApp' => 1,
                    'remainWarning' => 1,
                    'deviceError' => 1,
                    'reviewResult' => 1,
                    'agentMsg' => 1,
                ],
                'funcs' => Util::getAgentFNs(),
                'superior' => $superior_data,
                'location' => [
                    'validate' => [
                        'enabled' => App::isLocationValidateEnabled() ? 1 : 0,
                        'distance' => App::userLocationValidateDistance(),
                    ],
                ],
                'commission' => [
                    'fee_type' => 1,
                ]
            ];

            $user->updateSettings('agentData', $agent_data);
        }

        $user->setAgent($level);
        $user->setMobile($mobile);
    } elseif (request::has('agent_notice')) {

        if ($user->isAgent()) {
            $user->updateSettings(
                'agentData.notice',
                [
                    'agentApp' => request::bool('agentApp') ? 1 : 0,
                    'remainWarning' => request::bool('remainWarning') ? 1 : 0,
                    'deviceError' => request::bool('deviceError') ? 1 : 0,
                    'deviceOnline' => request::bool('deviceOnline') ? 1 : 0,
                    'reviewResult' => request::bool('reviewResult') ? 1 : 0,
                    'agentMsg' => request::bool('agentMsg') ? 1 : 0,
                ]
            );
        }
    } elseif (request::has('agent_funcs')) {

        if ($user->isAgent()) {
            $data = Util::parseAgentFNsFromGPC();
            $user->updateSettings('agentData.funcs', $data);

            if (App::isCustomWxAppEnabled()) {
                $user->updateSettings('agentData.wx.app', [
                    'key' => request::trim('WxAppKey'),
                ]);
            }
        }
    } elseif (request::has('agent_commission')) {

        if ($user->isAgent()) {

            $enabled = request::bool('commission');
            $user->updateSettings('agentData.commission.enabled', $enabled);
            if ($enabled) {
                $user->updateSettings('agentData.commission.fee_type', request::bool('feeType') ? 1 : 0);
                $user->updateSettings('agentData.commission.fee', request::float('commission_fee', 0, 2) * 100);
                $user->setPrincipal(User::GSPOR);
            }

            //佣金分享
            $gsp_enabled = request::bool('gsp_enabled');
            $user->updateSettings('agentData.gsp.enabled', $gsp_enabled);
            if ($gsp_enabled) {
                $gsp_mode = in_array(request::str('gsp_mode'), ['rel', 'free', 'mixed']) ? request::str('gsp_mode') : 'rel';
                $gsp_mode_type = request::str('gsp_mode_type', 'percent');
                $user->updateSettings('agentData.gsp.mode', $gsp_mode);
                $user->updateSettings('agentData.gsp.mode_type', $gsp_mode_type);

                if ($gsp_mode == 'rel') {
                    $user->updateSettings('agentData.gsp.order', [
                        'f' => request::bool('freeOrderGSP') ? 1 : 0,
                        'b' => request::bool('balanceOrderGSP') ? 1 : 0,
                        'p' => request::bool('payOrderGSP') ? 1 : 0,
                    ]);

                    $rel_1 = min(10000, max(0, request::float('rel_level1', 0, 2) * 100));
                    $rel_2 = min(10000, max(0, request::float('rel_level2', 0, 2) * 100));
                    $rel_3 = min(10000, max(0, request::float('rel_level3', 0, 2) * 100));

                    $user->updateSettings(
                        'agentData.gsp.rel',
                        [
                            'level1' => $rel_1,
                            'level2' => $rel_2,
                            'level3' => $rel_3,
                        ]
                    );
                }
            }

            //佣金奖励
            $bonus_enabled = request::bool('agentBonusEnabled');
            $user->updateSettings('agentData.bonus.enabled', $bonus_enabled);
            if ($bonus_enabled) {
                $user->updateSettings(
                    'agentData.bonus',
                    [
                        'enabled' => true,
                        'order' => [
                            'f' => request::bool('freeOrder') ? 1 : 0,
                            'b' => request::bool('balanceOrder') ? 1 : 0,
                            'p' => request::bool('payOrder') ? 1 : 0,
                        ],
                        'level0' => request::float('bonus_level0', 0, 2) * 100,
                        'level1' => request::float('bonus_level1', 0, 2) * 100,
                        'level2' => request::float('bonus_level2', 0, 2) * 100,
                        'level3' => request::float('bonus_level3', 0, 2) * 100,
                    ]
                );
            }
        }
    } elseif (request::bool('agent_misc')) {

        if ($user->isAgent()) {
            $user->updateSettings(
                'agentData.misc',
                [
                    'maxTotalFree' => request::int('maxTotalFree'),
                    'maxFree' => request::int('maxFree'),
                    'maxAccounts' => request::int('maxAccounts'),
                    'pushAccountMsg' => request::trim('pushAccountMsg'),
                    'siteTitle' => request::trim('siteTitle'),
                    'siteLogo' => request::trim('image'),
                    'power' => request::int('power'),
                    'auto_ref' => request::int('auto_ref')
                ]
            );

            $user->updateSettings(
                'agentData.device',
                [
                    'theme' => request::str('theme'),
                    'remainWarning' => request::int('remainWarning'),
                    'shipment' => [
                        'balanced' => request::bool('shipmentBalance') ? 1 : 0,
                    ]
                ]
            );

            $locationEnabled = request('locationEnabled') ? 1 : 0;
            $user->updateSettings('agentData.location.validate.enabled', $locationEnabled);
            if ($locationEnabled) {
                $user->updateSettings('agentData.location.validate.distance', request::int('locationDistance'));
            }

            if (App::isMustFollowAccountEnabled()) {
                $user->updateSettings('agentData.mfa', [
                    'enable' => request::int('mustFollow'),
                ]);
            }

            if (App::isZeroBonusEnabled()) {
                $user->updateSettings('agentData.custom.bonus.zero.v', min(100, request::float('zeroBonus', -1, 2)));
            }
        }
    } elseif (request::bool('agent_payment')) {
        if ($user->isAgent()) {
            $data = $user->settings('agentData.pay', []);

            $wx_enabled = request('wx') ? 1 : 0;
            $data['wx']['enable'] = $wx_enabled;
            if ($wx_enabled) {
                $data['wx']['appid'] = request::trim('wxAppID');
                $data['wx']['wxappid'] = request::trim('wxxAppID');
                $data['wx']['key'] = request::trim('wxAppKey');
                $data['wx']['mch_id'] = request::trim('wxMCHID');
                $data['wx']['pem'] = [
                    'cert' => request::trim('certPEM'),
                    'key' => request::trim('keyPEM'),
                ];
            }

            $lcsw_enabled = request::bool('lcsw');
            $data['lcsw']['enable'] = $lcsw_enabled;
            if ($lcsw_enabled) {
                $data['lcsw']['merchant_no'] = request::trim('merchant_no');
                $data['lcsw']['terminal_id'] = request::trim('terminal_id');
                $data['lcsw']['access_token'] = request::trim('access_token');

                //创建扫呗接口文件
                Util::createApiRedirectFile('payment/lcsw.php', 'payresult', [
                    'headers' => [
                        'HTTP_USER_AGENT' => 'lcsw_notify',
                    ],
                    'op' => 'notify',
                    'from' => 'lcsw',
                ]);
            }

            $user->updateSettings('agentData.pay', $data);
        }
    }

    if ($user->save()) {
        if ($agentEdit) {
            Util::itoast('保存成功！', $this->createWebUrl('agent', ['op' => request('from'), 'id' => $id]), 'success');
        } else {
            //使用控制中心推送通知
            Job::newAgent($user->getId());
            Util::itoast('代理商设置成功！', $this->createWebUrl('agent', ['op' => request::str('from'), 'id' => $id]), 'success');
        }
    }

    Util::itoast('保存失败！', $this->createWebUrl('agent', ['op' => request::str('from'), 'id' => $id]), 'error');
} elseif ($op == 'enableSQB') {

    $agent = Agent::get(request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $app_id = request::trim('app_id');
    $vendor_sn = request::trim('vendor_sn');
    $vendor_key = request::trim('vendor_key');
    $code = request::trim('code');

    $result = SQB::activate($app_id, $vendor_sn, $vendor_key, $code);

    if (is_error($result)) {
        JSON::fail($result);
    }

    if ($agent->updateSettings('agentData.pay.SQB', [
        'enable' => 1,
        'sn' => $result['terminal_sn'],
        'key' => $result['terminal_key'],
        'title' => $result['store_name']
    ])) {
        JSON::success('成功！');
    }

    JSON::success('失败！');
} elseif ($op == 'disableSQB') {

    $agent = Agent::get(request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    if ($agent->updateSettings('agentData.pay.SQB', [])) {
        JSON::success('成功！');
    }

    JSON::fail('失败！');
} elseif ($op == 'agent_remove') {

    $from = request::trim('from') ?: 'agent';
    $user_id = request::int('id');

    if ($user_id) {
        $res = Util::transactionDo(
            function () use ($user_id) {
                $agent = Agent::get($user_id);
                if ($agent) {
                    return Agent::remove($agent);
                }
                return err('找不到这个代理商！');
            }
        );

        if (!is_error($res)) {
            Util::itoast('已取消用户代理身份！', $this->createWebUrl($from), 'success');
        }
    }

    Util::itoast(empty($res['message']) ? '操作失败！' : $res['message'], $this->createWebUrl($from), 'error');
} elseif ($op == 'partner_remove') {

    $from = request::trim('from') ?: 'user';
    $user_id = request::int('id');

    if ($user_id) {
        $res = Util::transactionDo(function () use ($user_id) {
            $user = User::get($user_id);
            if (empty($user)) {
                return error(State::ERROR, '找不到这个用户！');
            }
            if (!$user->isPartner()) {
                return error(State::ERROR, '用户不是任何代理商的合伙人！');
            }

            $agent = $user->getPartnerAgent();
            if ($agent) {
                if ($agent->removePartner($user)) {
                    return ['message' => '成功！'];
                }
            }

            return error(State::ERROR, '失败！');
        });

        if (!is_error($res)) {
            Util::itoast('已取消用户代理身份！', $this->createWebUrl($from), 'success');
        }
    }

    Util::itoast(empty($res['message']) ? '操作失败！' : $res['message'], $this->createWebUrl($from), 'error');
} elseif ($op == 'search') {

    $query = Principal::agent();
    $id = request::int('id');
    if ($id) {
        $query->where(['id <>' => $id]);
    }

    $openid = request::str('openid', '', true);
    if ($openid) {
        $query->where(['openid' => $openid]);
    }

    $keyword = request::str('keyword', '', true);
    if ($keyword) {
        $query->whereOr([
            'name REGEXP' => $keyword,
            'nickname REGEXP' => $keyword,
            'mobile REGEXP' => $keyword,
        ]);
    }

    $query->limit(20);

    $result = [];
    /** @var  userModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $result[] = [
            'id' => $entry->getId(),
            'openid' => $entry->getOpenid(),
            'nickname' => $entry->getNickname(),
            'name' => $entry->getName(),
            'company' => $entry->settings('agentData.company', '未登记'),
            'mobile' => $entry->getMobile(),
            'avatar' => $entry->getAvatar(),
        ];
    }

    JSON::success($result);
} elseif ($op == 'viewStatsChart') {

    $agent = Agent::get(request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $datetime = DateTime::createFromFormat('Y年m月', request::trim('month'));
    if (!$datetime) {
        $datetime = new DateTime();
    }

    $title = $datetime->format('Y年n月');
    $content = app()->fetchTemplate(
        'web/agent/stats',
        [
            'chartid' => Util::random(10),
            'title' => $title,
            'chart' => Util::cachedCall(30, function () use ($agent, $datetime, $title) {
                return Stats::chartDataOfMonth($agent, $datetime, "代理商：{$agent->getName()}($title)");
            }, $agent->getId(), $title),
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);
} elseif ($op == 'viewstats') {

    $agent = Agent::get(request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $result = Util::cachedCall(30, function () use ($agent) {
        $first_order = Order::getFirstOrderOfAgent($agent);
        if (empty($first_order)) {
            return error(State::ERROR, '代理商暂时没有任何订单！');
        }

        $last_order = Order::getLastOrderOfAgent($agent);

        $months = [];

        try {
            $begin = new DateTime(date('Y-m-d H:i:s', $first_order->getCreatetime()));
            $end = new DateTime(date('Y-m-d H:i:s', $last_order->getCreatetime()));

            $end = $end->modify('first day of next month');
            $end->modify('-1 day');
            do {
                $months[$begin->format('Y年m月')] = $agent->getMTotal($begin->format('Y-m'));
                $begin->modify('first day of next month');
            } while ($begin < $end);
        } catch (Exception $e) {
            return error(State::ERROR, '获取数据失败！');
        }
        return $months;
    }, $agent->getId());

    $content = app()->fetchTemplate(
        'web/agent/agent-stats',
        [
            'agent' => $agent,
            'm_all' => is_error($result) ? [] : $result,
        ]
    );

    JSON::success(['title' => "<b>{$agent->getName()}</b>的出货统计", 'content' => $content]);
} elseif ($op == 'repair') {

    $agent = Agent::get(request::int('id'));
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $month = request::str('month');
    $date = DateTimeImmutable::createFromFormat('Y年m月d H:i:s', $month . '01 00:00:00');
    if ($date === false) {
        JSON::fail('时间格式不正确！');
    }

    $result = Util::cachedCall(3, function () use ($agent, $date) {
        $result = Stats::repairMonthData($agent, $date);
        if (!is_error($result)) {
            if ($agent->settings('repair.status'))
                $agent->updateSettings('repair', [
                    'status' => 'finished',
                    'time' => time(),
                ]);
        }
        return $result;
    }, $agent->getId(), $month);

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('修复完成！');
} elseif ($op == 'viewmsg') {

    $agent_id = request::isset('id') ? request::int('id') : request::int('agentid');
    $agent = User::get($agent_id);
    if (empty($agent) || !($agent->isAgent() || $agent->isPartner())) {
        JSON::fail('找不到这个代理商！');
    }

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);
    $pager = '';

    $query = m('agent_msg')->where(We7::uniacid(['agent_id' => $agent->getId()]));

    $total = $query->count();
    $messages = [];

    if ($total > 0) {
        $total_page = ceil($total / $page_size);
        if ($page > $total_page) {
            $page = 1;
        }

        $pager = We7::pagination($total, $page, $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id desc');

        /** @var agent_msgModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $messages[] = [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'updatetime' => $entry->getUpdatetime(),
            ];
        }
    }

    $content = app()->fetchTemplate(
        'web/agent/agent-msg',
        [
            'agent' => $agent,
            'message' => $messages,
            'pager' => $pager,
        ]
    );

    JSON::success(['title' => "<b>{$agent->getName()}</b>的消息", 'content' => $content]);
} elseif ($op == 'partner') {

    $id = request::int('id');
    $agent = Agent::get($id);
    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
    }

    $level = $agent->getAgentLevel();
    $partners = [];

    foreach ($agent->settings('agentData.partners', []) as $partner_id => $data) {
        $user = User::get($partner_id);
        if ($user) {
            $partners[] = [
                'id' => $user->getId(),
                'nickname' => $user->getNickname(),
                'avatar' => $user->getAvatar(),
                'name' => $data['name'] ?: '&lt;未登记&gt;',
                'mobile' => $user->getMobile(),
                'createtime' => date('Y-m-d H:i:s', $data['createtime']),
            ];
        }
    }

    app()->showTemplate('web/agent/partner', [
        'op' => $op,
        'id' => $id,
        'agent' => $agent,
        'level' => $level,
        'partners' => $partners,
    ]);
} elseif ($op == 'partneradd') {

    $agent_id = request::int('agentid');
    $user_id = request::int('userid');

    $back_url = $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]);

    if ($agent_id == $user_id) {
        Util::itoast('合伙人不能是自己！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    $agent = Agent::get($agent_id);

    $level = $agent->getAgentLevel();
    $user = User::get($user_id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    if ($user->isAgent() || $user->isPartner()) {
        Util::itoast('该用户已经是代理商或合伙人！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    $partner_data['mobile'] = $user->getMobile();

    app()->showTemplate('web/agent/partner-edit', [
        'op' => $op,
        'agent_id' => $agent_id,
        'user_id' => $user_id,
        'back_url' => $back_url,
        'agent' => $agent,
        'level' => $level,
        'user' => $user,
        'partnerData' => $partner_data,
    ]);
} elseif ($op == 'partneredit') {

    $agent_id = request::int('agentid');
    $user_id = request::int('partnerid') ?: request::int('userid');

    $back_url = $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]);

    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
    }

    $level = $agent->getAgentLevel();

    $user = User::get($user_id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    $partner_data = $user->get('partnerData', []);

    $agent_data = $agent->getAgentData();
    $notice = $agent_data['partners'][$user->getId()]['notice'] ?: [];

    app()->showTemplate('web/agent/partner-edit', [
        'op' => $op,
        'agent_id' => $agent_id,
        'user_id' => $user_id,
        'back_url' => $back_url,
        'agent' => $agent,
        'level' => $level,
        'user' => $user,
        'partnerData' => $partner_data,
        'agentData' => $agent_data,
        'notice' => $notice,
    ]);
} elseif ($op == 'partnerremove') {

    $agent_id = request::int('agentid');
    $partner_id = request::int('partnerid');

    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    $res = Util::transactionDo(
        function () use ($agent, $partner_id) {

            foreach (m('agent_msg')->where(We7::uniacid(['agent_id' => $partner_id]))->findAll() as $msg) {
                $msg->destroy();
            }

            if ($agent->removePartner($partner_id)) {
                return true;
            }

            return error(State::ERROR, 'fail');
        }
    );

    if (is_error($res)) {
        Util::itoast('合伙人删除失败！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    Util::itoast('合伙人删除成功！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'success');
} elseif ($op == 'partnersave') {

    $from = request::str('from') ?: 'partneradd';

    $agent_id = request::int('agentid');
    $user_id = request::int('userid');

    $agent = Agent::get($agent_id);
    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', $this->createWebUrl('agent'), 'error');
    }

    $user = User::get($user_id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', $this->createWebUrl('agent', ['op' => 'partner', 'agentid' => $agent_id]), 'error');
    }

    $name = request::trim('name');
    $mobile = request::trim('mobile');

    $notice = [
        'agentApp' => request('agentApp') ? 1 : 0,
        'remainWarning' => request('remainWarning') ? 1 : 0,
        'deviceError' => request('deviceError') ? 1 : 0,
        'reviewResult' => request('reviewResult') ? 1 : 0,
        'agentMsg' => request('agentMsg') ? 1 : 0,
    ];

    if (empty($mobile)) {
        Util::itoast(
            '请填写合伙人的手机号码！',
            $this->createWebUrl('agent', ['op' => $from, 'agentid' => $agent_id, 'userid' => $user_id]),
            'error'
        );
    }

    $res = User::findOne(['id <>' => $user_id, 'mobile' => $mobile]);
    if ($res) {
        Util::itoast(
            '手机号码已经被其它用户使用！',
            $this->createWebUrl('agent', ['op' => $from, 'agentid' => $agent_id, 'userid' => $user_id]),
            'error'
        );
    }

    $res = Util::transactionDo(
        function () use ($agent, $user, $name, $mobile, $notice) {
            if ($agent->setPartner($user, $name, $mobile, $notice)) {
                return true;
            }

            return error(State::ERROR, 'fail');
        }
    );

    if (is_error($res)) {
        Util::itoast('合伙人保存失败！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'error');
    }

    Util::itoast('合伙人保存成功！', $this->createWebUrl('agent', ['op' => 'partner', 'id' => $agent_id]), 'success');
} elseif ($op == 'app') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $query = m('agent_app')->where(We7::uniacid([]));

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    if ($page > $total_page) {
        $page = 1;
    }

    $pager = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $apps = [];

    /** @var agent_appModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $apps[] = [
            'id' => $entry->getId(),
            'name' => $entry->getName(),
            'mobile' => $entry->getMobile(),
            'address' => $entry->getAddress(),
            'referee' => $entry->getReferee(),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'state' => $entry->getState(),
        ];
    }

    app()->showTemplate('web/agent/app', [
        'op' => $op,
        'pager' => $pager,
        'apps' => $apps,
    ]);
} elseif ($op == 'appStateChecked') {

    $id = request::int('id');
    /** @var agent_appModelObj $app */
    $app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));
    if ($app) {
        $state = $app->getState() != AgentApp::CHECKED ? AgentApp::CHECKED : AgentApp::WAIT;
        $app->setState($state);
        if ($app->save()) {
            Util::itoast('设置成功！', $this->createWebUrl('agent', ['op' => 'app']), 'success');
        }
    }

    Util::itoast('设置失败！', $this->createWebUrl('agent', ['op' => 'app']), 'error');
} elseif ($op == 'forwardapp') {

    $tp_lid = settings('notice.agentReq_tplid');
    if (empty($tp_lid)) {
        JSON::fail('请先设置消息模板ID，否则无法推送通知!');
    }

    $agent_ids = request('agentids');
    $id = request::int('id');

    if (empty($agent_ids) || empty($id)) {
        JSON::fail('请求参数不正确！');
    }

    $app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));
    if ($app) {
        if (Job::agentAppForward($app->getId(), $agent_ids)) {
            JSON::success('已提交请求到控制中心！');
        }
    }

    JSON::fail('转发请求处理失败！');
} elseif ($op == 'appRemove') {

    $id = request::int('id');
    $app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));

    if ($app && $app->destroy()) {
        Util::itoast('删除成功！', $this->createWebUrl('agent', ['op' => 'app']), 'success');
    }

    Util::itoast('删除失败！', $this->createWebUrl('agent', ['op' => 'app']), 'error');
} elseif ($op == 'msg') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $query = m('msg')->where(We7::uniacid([]));

    $total = $query->count();
    $total_page = ceil($total / $page_size);
    if ($page > $total_page) {
        $page = 1;
    }

    $pager = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $messages = [];

    /** @var msgModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $messages[] = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
    }

    app()->showTemplate('web/agent/msg', [
        'op' => $op,
        'pager' => $pager,
        'messages' => $messages,
    ]);
} elseif ($op == 'msgadd' || $op == 'msgedit') {

    $tpl_data = [
        'op' => $op,
    ];
    if ($op == 'msgedit') {
        $id = request::int('id');
        if ($id) {
            $tpl_data['id'] = $id;
            $tpl_data['msg'] = m('msg')->findOne(We7::uniacid(['id' => $id]));
        }
    }

    app()->showTemplate('web/agent/msg-edit', $tpl_data);
} elseif ($op == 'msgsave') {

    $id = request::int('id');

    $title = request::trim('title');
    $content = request::str('content');

    if ($id) {
        /** @var msgModelObj $msg */
        $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
        if ($msg) {
            if ($msg->getTitle() != $title) {
                $msg->setTitle($title);
            }

            if ($msg->getContent() != $content) {
                $msg->setContent($content);
            }
        }
    }

    if (empty($msg)) {
        $msg = m('msg')->create(We7::uniacid(['title' => $title, 'content' => $content]));
    }

    if ($msg && $msg->save()) {
        Util::itoast('保存成功！', $this->createWebUrl('agent', ['op' => 'msg']), 'success');
    }

    Util::itoast('保存失败！', $this->createWebUrl('agent', ['op' => 'msg']), 'error');
} elseif ($op == 'msgremove') {

    $id = request::int('id');
    if ($id) {
        $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
        if ($msg) {
            $msg->destroy();
            Util::itoast('删除成功！', $this->createWebUrl('agent', ['op' => 'msg']), 'success');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('agent', ['op' => 'msg']), 'error');
} elseif ($op == 'sendMsg') {

    $tp_lid = settings('agent.msg_tplid');
    if (empty($tp_lid)) {
        JSON::fail('请先设置消息模板ID，否则无法推送提醒消息!');
    }

    $agent_ids = request('agentids');
    $id = request::int('id');

    if (empty($agent_ids) || empty($id)) {
        JSON::fail('请求参数不正确！');
    }

    $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
    if ($msg && $msg->set('agents', $agent_ids)) {
        $res = Job::agentMsgNotice($msg->getId());
        if (!is_error($res)) {
            JSON::success('已发送请求到控制中心！');
        }
    }

    JSON::fail('请求发送提醒消息失败！');
} elseif ($op == 'msglist') {

    $id = request::int('id');
    $user = User::get($id);
    if (empty($user) || !($user->isAgent() || $user->isPartner())) {
        Util::itoast('用户不是代理商或者代理商合伙人！', We7::referer(), 'error');
    }

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $query = m('agent_msg')->where(We7::uniacid(['agent_id' => $id]));

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    if ($page > $total_page) {
        $page = 1;
    }

    $messages = [];

    if ($total > 0) {
        $query->page($page, $page_size);
        $query->orderBy('id desc');

        /** @var agent_msgModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $messages[] = [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'updatetime' => $entry->getUpdatetime(),
            ];
        }
    }

    $tpl_data[] = [
        'op' => $op,
        'id' => $id,
        'user' => $user,
        'messages' => $messages,
    ];

    $from = request::str('from');
    if ($from == 'agent') {
        $tpl_data['back_url'] = $this->createWebUrl('agent', ['id' => $id]);
    } elseif ($from == 'partner') {
        $tpl_data['back_url'] = $this->createWebUrl('agent', ['op' => 'partner', 'id' => $user->getAgentId()]);
    }

    app()->showTemplate('web/agent/agent-msg', $tpl_data);
} elseif ($op == 'msglist_remove') {

    $id = request::int('id');

    /** @var agent_msgModelObj $msg */
    $msg = m('agent_msg')->findOne(We7::uniacid(['id' => $id]));

    if ($msg) {
        $agent_id = $msg->getAgentId();
        $user = User::get($agent_id);
        $from = $user->isAgent() ? 'agent' : 'partner';

        $msg->destroy();
        Util::itoast(
            '删除成功！',
            $this->createWebUrl('agent', ['op' => 'msglist', 'id' => $agent_id, 'from' => $from]),
            'success'
        );
    }

    Util::itoast('删除失败！', We7::referer(), 'error');
} elseif ($op == 'msglist_detail') {

    $id = request::int('id');
    /** @var msgModelObj $msg */
    $msg = m('agent_msg')->findOne(We7::uniacid(['id' => $id]));
    if ($msg) {
        JSON::success(['title' => $msg->getTitle(), 'content' => html_entity_decode($msg->getContent())]);
    }

    JSON::fail('出错了，无法读取消息内容！');
} elseif ($op == 'msg_detail') {

    $id = request::int('id');
    /** @var msgModelObj $msg */
    $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
    if ($msg) {
        JSON::success(['title' => $msg->getTitle(), 'content' => html_entity_decode($msg->getContent())]);
    }

    JSON::fail('出错了，无法读取消息内容！');
} elseif ($op == 'gsp') {

    $result_msg = function ($msg, $status) {
        if (request::is_ajax()) {
            if ($status) {
                JSON::success($msg);
            } else {
                JSON::fail($msg);
            }
        } else {
            Util::itoast($msg, $status ? 'success' : 'error');
        }
    };

    $agent = Agent::get(request::int('agentid'));
    if (empty($agent)) {
        $result_msg('找不到这个代理商！', false);
    }

    if (empty($agent->isCommissionEnabled())) {
        $result_msg('代理商没有加入佣金系统！', false);
    }

    $tpl_data = [
        'op' => $op,
        'agent' => $agent,
    ];

    $back_url = $this->createWebUrl('agent', array('id' => $agent->getId(), 'op' => 'agent_commission'));
    $tpl_data['back_url'] = $back_url;

    $fn = request::trim('fn');
    if ($fn == 'adduser' || $fn == 'edituser') {
        $from = request::trim('from');
        if ($from == 'free') {
            $user = User::get(request::int('id'));
            if (empty($user)) {
                $result_msg('找不到这个用户！', false);
            }

            $tpl_data['user'] = $user;

            if ($fn == 'adduser') {
                $tpl_data['order_type'] = [
                    'f' => 1,
                    'b' => 1,
                    'p' => 1,
                ];
                $tpl_data['mode'] = request::str('mode', 'percent');
                if ($agent->settings("agentData.gsp.users.{$user->getOpenid()}")) {
                    $result_msg('用户已经是代理商的佣金分享用户！', false);
                }
            } elseif ($fn == 'edituser') {
                $data = $agent->settings("agentData.gsp.users.{$user->getOpenid()}", []);
                $tpl_data['order_type'] = is_array($data['order']) ? $data['order'] : [
                    'f' => 1,
                    'b' => 1,
                    'p' => 1,
                ];
                if ($data['percent']) {
                    $tpl_data['mode_type'] = 'percent';
                    $tpl_data['val'] = number_format($data['percent'] / 100, 2);
                } else {
                    $tpl_data['mode_type'] = 'amount';
                    $tpl_data['val'] = number_format($data['amount'] / 100, 2);
                }
            }
            app()->showTemplate('web/agent/free-edit-user', $tpl_data);
        } elseif ($from == 'mixed') {
            if ($fn == 'adduser') {
                $user = User::get(request::int('id'));
            } else {
                $entry = GSP::findOne(['agent_id' => $agent->getId(), 'id' => request::int('id')]);
                if (empty($entry)) {
                    $result_msg('找不到这个设置！', false);
                }

                if ($entry->isRole()) {
                    $result_msg('不能编辑', false);
                }

                $user = User::get($entry->getUid(), true);
            }

            if (empty($user)) {
                $result_msg('找不到这个用户！', false);
            }
            $tpl_data['user'] = $user;

            app()->showTemplate('web/agent/mixed-edit-user', $tpl_data);
        } else {
            Util::resultAlert('不正确的操作！', 'error');
        }
    } elseif ($fn == 'saveuser') {

        $user = User::get(request::int('id'));
        if (empty($user)) {
            Util::message('找不到这个用户！', $back_url, 'error');
        }

        $from = request::trim('from', 'free');
        if ($from == 'free') {
            $order_type = [
                'f' => request::bool('freeOrder') ? 1 : 0,
                'b' => request::bool('balanceOrder') ? 1 : 0,
                'p' => request::bool('payOrder') ? 1 : 0,
            ];

            $key_name = "agentData.gsp.users.{$user->getOpenid()}";

            $agent->updateSettings("$key_name.order", $order_type);

            $mode_type = request::trim('mode_type', 'percent');
            if ($mode_type == 'percent') {
                $percent = min(10000, max(0, request::float('val', 0, 2) * 100));
                if ($agent->settings($key_name)) {
                    $agent->updateSettings("$key_name.percent", $percent);
                } else {
                    $agent->updateSettings(
                        $key_name,
                        [
                            'percent' => $percent,
                            'createtime' => time(),
                        ]
                    );
                }
                $agent->updateSettings("$key_name.amount", []);
            } else {
                $amount = request::float('val', 0, 2) * 100;
                if ($agent->settings($key_name)) {
                    $agent->updateSettings("$key_name.amount", $amount);
                } else {
                    $agent->updateSettings(
                        $key_name,
                        [
                            'amount' => $amount,
                            'createtime' => time(),
                        ]
                    );
                }
                $agent->updateSettings("$key_name.percent", []);
            }

            $agent->updateSettings('agentData.gsp.enabled', 1);
            $agent->updateSettings('agentData.gsp.mode', 'free');
        } elseif ($from == 'mixed') {
            $data = [
                'agent_id' => $agent->getId(),
                'uid' => $user->getOpenid(),
            ];

            $entries = [];
            foreach ([
                         'f' => ['type' => 'freeOrderType', 'val' => 'freeOrderVal'],
                         'b' => ['type' => 'balanceOrderType', 'val' => 'balanceOrderVal'],
                         'p' => ['type' => 'payOrderType', 'val' => 'payOrderVal'],
                     ] as $key => $v) {
                if (request::isset($v['type'])) {
                    if (request::bool($v['type'])) {
                        $entries[$key] = [
                            'val_type' => 'percent',
                            'val' => min(10000, max(0, request::float($v['val'], 0, 2) * 100)),
                        ];
                    } else {
                        $entries[$key] = [
                            'val_type' => 'amount',
                            'val' => request::float($v['val'], 0, 2) * 100,
                        ];
                    }
                }
            }
            foreach ($entries as $order_type => $entry) {
                $data['val_type'] = $entry['val_type'];
                $data['val'] = $entry['val'];
                $data['order_types'] = $order_type;
                GSP::update(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid(), 'order_types' => $order_type], $data);
            }
            $agent->updateSettings('agentData.gsp.enabled', 1);
            $agent->updateSettings('agentData.gsp.mode', 'mixed');
        }

        Util::message('保存成功！', $back_url, 'success');
    } elseif ($fn == 'removeuser') {
        $from = request::trim("from");
        if ($from == 'free') {
            $user = User::get(request::int('id'));
            if (empty($user)) {
                $result_msg('找不到这个用户', 'error');
            }
            if ($agent->settings("agentData.gsp.users.{$user->getOpenid()}")) {
                if ($agent->removeSettings('agentData.gsp.users', $user->getOpenid())) {
                    $result_msg('删除成功！', true);
                }
            }
        } elseif ($from == 'mixed') {
            $entry = GSP::findOne(['agent_id' => $agent->getId(), 'id' => request::int('id')]);
            if (empty($entry)) {
                $result_msg('找不到这个设置', 'error');
            }
            if (!$entry->isRole()) {
                $user = User::get($entry->getUid(), true);
                if (empty($user)) {
                    $result_msg('找不到这个用户', 'error');
                }
                $query = GSP::query(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid()]);
                foreach ($query->findAll() as $entry) {
                    $entry->destroy();
                }
            }

            $result_msg('删除成功！', true);
        }


        $result_msg('删除失败！', false);
    } elseif ($fn == 'add_role') {
        $tpl_data['agentId'] = $agent->getId();

        $tpl_data['level'] = request::trim('level');

        $content = app()->fetchTemplate('web/agent/gsp-add-role', $tpl_data);
        JSON::success([
            'title' => '设置角色',
            'content' => $content,
        ]);
    } elseif ($fn == 'get_role') {
        $level = request::str('level');
        if (!in_array($level, [GSP::LEVEL1, GSP::LEVEL2, GSP::LEVEL3])) {
            JSON::fail('角色不正确！');
        }
        $result = [];
        $f = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $level, 'order_types' => 'f']);
        if ($f) {
            $result['f'] = [
                'val_type' => $f->getValType(),
                'val' => number_format($f->getVal() / 100, 2),
            ];
        }
        $p = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $level, 'order_types' => 'p']);
        if ($p) {
            $result['p'] = [
                'val_type' => $p->getValType(),
                'val' => number_format($p->getVal() / 100, 2),
            ];
        }
        JSON::success($result);
    } elseif ($fn == 'save_role') {
        $level = request::str('level');
        if (!in_array($level, [GSP::LEVEL1, GSP::LEVEL2, GSP::LEVEL3])) {
            JSON::fail('角色不正确！');
        }
        $data = [
            'agent_id' => $agent->getId(),
            'uid' => $level,
        ];
        $entries = [];
        foreach ([
                     'f' => ['type' => 'freeOrderType', 'val' => 'freeOrderVal'],
                     'b' => ['type' => 'balanceOrderType', 'val' => 'balanceOrderVal'],
                     'p' => ['type' => 'payOrderType', 'val' => 'payOrderVal'],
                 ] as $key => $v) {
            if (request::isset($v['type'])) {
                if (request::bool($v['type'])) {
                    $entries[$key] = [
                        'val_type' => 'percent',
                        'val' => min(10000, max(0, request::float($v['val'], 0, 2) * 100)),
                    ];
                } else {
                    $entries[$key] = [
                        'val_type' => 'amount',
                        'val' => request::float($v['val'], 0, 2) * 100,
                    ];
                }
            }
        }
        foreach ($entries as $order_type => $entry) {
            $data['val_type'] = $entry['val_type'];
            $data['val'] = $entry['val'];
            $data['order_types'] = $order_type;
            GSP::update(['agent_id' => $agent->getId(), 'uid' => $level, 'order_types' => $order_type], $data);
        }

        $agent->updateSettings('agentData.gsp.enabled', 1);
        $agent->updateSettings('agentData.gsp.mode', 'mixed');

        JSON::success('成功！');
    } elseif ($fn == 'get_data') {
        $user = User::get(request::trim('openid'), true);
        if (empty($user)) {
            JSON::fail('找不到这个用户！');
        }
        $result = [];
        $f = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid(), 'order_types' => 'f']);
        if ($f) {
            $result['f'] = [
                'val_type' => $f->getValType(),
                'val' => number_format($f->getVal() / 100, 2),
            ];
        }
        $p = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid(), 'order_types' => 'p']);
        if ($p) {
            $result['p'] = [
                'val_type' => $p->getValType(),
                'val' => number_format($p->getVal() / 100, 2),
            ];
        }
        JSON::success($result);
    }
} elseif ($op == 'keepers') {

    $id = request::int('id');

    $agent = Agent::get($id);
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $query = Keeper::query(['agent_id' => $agent->getId()]);

    $result = [];
    /** @var keeperModelObj $keeper */
    foreach ($query->findAll() as $keeper) {
        $user = $keeper->getUser();
        $result[] = [
            'user' => empty($user) ? [] : $user->profile(),
            'name' => $keeper->getName(),
            'mobile' => $keeper->getMobile(),
            'devices_total' => intval($keeper->deviceQuery()->count()),
            'createtime' => date('Y-m-d H:i:s', $keeper->getCreatetime()),
        ];
    }

    app()->showTemplate(
        'web/agent/keepers',
        [
            'agent' => $agent->profile(),
            'list' => $result,
            'back_url' => Util::url('agent', []),
        ]
    );
} elseif ($op == 'refresh_rel') {

    if (!YZShop::isInstalled()) {
        JSON::fail('刷新失败！');
    }

    $page = max(1, request::int('page'));

    $query = Agent::query();

    $count = $query->count();

    $query->page($page, DEFAULT_PAGE_SIZE);

    /** @var agentModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $superior = YZShop::getSuperior($entry);
        if ($superior) {
            if ($entry->getSuperiorId() != $superior->getId()) {
                $entry->setSuperiorId($superior->getId());
                $entry->save();
            }
        } else {
            if ($entry->getSuperiorId()) {
                $entry->setSuperiorId(0);
                $entry->save();
            }
        }
    }

    JSON::success(['more' => $page * DEFAULT_PAGE_SIZE < $count ? 'y' : 'n']);
} elseif ($op == 'detail') {

    $pages = [
        'default' => [
            'title' => '首页',
        ],
        'devices' => [
            'title' => '设备列表',
        ],
        'accounts' => [
            'title' => '吸粉广告',
        ],
        'advs' => [
            'title' => '其它广告',
        ],
        'orders' => [
            'title' => '订单列表',
        ],
        'commission' => [
            'title' => '佣金明细',
        ],
        'withdraw' => [
            'title' => '提现记录',
        ],
        'partner' => [
            'title' => '合伙人',
        ],
        'keepers' => [
            'title' => '运营人员',
        ],
        'statistics' => [
            'title' => '统计数据',
        ],
    ];

    $id = request::int('id');
    $agent = Agent::get($id);
    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', 'error');
    }

    $page_name = request::trim('page_name', 'default');

    app()->showTemplate("web/agent/detail/$page_name", [
        'agent' => $agent,
        'pages' => $pages,
        'id' => $id,
        'page_name' => $page_name,
    ]);
} elseif ($op == 'commission_export') {

    $s_user_list = [];

    $query = Principal::gspsor();
    $s_keyword = request::trim('keyword', '', true);
    if ($s_keyword != '') {
        $query = $query->whereOr([
            'name REGEXP' => $s_keyword,
            'nickname REGEXP' => $s_keyword,
            'mobile REGEXP' => $s_keyword,
        ]);
    }

    $query->limit(20);

    /** @var userModelObj $val */
    foreach ($query->findAll() as $val) {
        $s_user_list[] = $val;
    }

    $date_limit = [
        'start' => request::str('start'),
        'end' => request::str('end'),
    ];

    if ($date_limit['start']) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'] . ' 00:00:00');
    } else {
        $s_date = new DateTime('first day of this month 00:00:00');
    }

    if ($date_limit['end']) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'] . ' 00:00:00');
        $e_date->modify('next day');
    } else {
        $e_date = new DateTime('first day of next month 00:00:00');
    }

    $s_openid = request::str('agent_openid');
    if ($s_openid) {
        $user = User::get($s_openid, true);
        if (empty($user)) {
            Util::itoast('找不到这个用户！', '', 'error');
        }
    }

    $cond = [
        'createtime >=' => $s_date->getTimestamp(),
        'createtime <' => $e_date->getTimestamp(),
    ];

    //是否导出
    if (request::bool('is_export')) {
        if (empty($user)) {
            Util::itoast('请指定用户！', '', 'error');
        }

        set_time_limit(60);

        //导出
        $logs = [];
        $title_arr = [
            '#',
            '金额',
            '时间',
            '事件',
            '设备',
            '公众号',
        ];

        $file_name = $user->getName() . '的数据';
        $query = $user->getCommissionBalance()->log();
        $query->where($cond);
        $query->orderBy('createtime DESC');

        /** @var commission_balanceModelObj $entry */
        foreach ($query->findAll() as $index => $entry) {
            $data = [
                'id' => $index + 1,
                'xval' => number_format($entry->getXVal() / 100, 2),
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'event' => '',  //事件
                'device' => '',  //设备
                'wx_account' => '', //公众号
            ];
            if ($entry->getXVal() > 0) {
                $data['xval'] = '+' . $data['xval'];
            }

            if ($entry->getSrc() == CommissionBalance::WITHDRAW) {
                $status = $entry->getState();
                $data['event'] = '佣金提现' . $status;
            } elseif ($entry->getSrc() == CommissionBalance::REFUND) {
                $data['event'] = '退款';
            } elseif (in_array(
                $entry->getSrc(),
                [
                    CommissionBalance::ORDER_FREE,
                    CommissionBalance::ORDER_BALANCE,
                    CommissionBalance::ORDER_WX_PAY,
                ]
            )) {
                $order_id = $entry->getExtraData('orderid');
                $order = Order::get($order_id);
                if ($order) {
                    $device = Device::get($order->getDeviceId());
                    $goods = Goods::data($order->getGoodsId());
                    if ($order->getPrice() > 0) {
                        $pay_type = User::getUserCharacter($order->getOpenid())['title'];
                        $spec = $pay_type . "：￥" . number_format($order->getPrice() / 100, 2) . "元 购买：" . $goods['name'] . "x" . $order->getNum();
                    } elseif ($order->getBalance() > 0) {
                        $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                        $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                        $spec = "使用" . $order->getBalance() . $unit_title . $balance_title . "购买：" . $goods['name'] . "x" . $order->getNum();
                    } else {
                        $spec = "免费领取：" . $goods['name'] . "x" . $order->getNum();
                    }
                    $account_name = $order->getAccount();
                    if ($account_name) {
                        $data['wx_account'] = $account_name;
                    }
                    $device_name = $device ? $device->getName() : '未知';
                    $data['event'] = $spec;
                    $data['device'] = $device_name;
                } else {
                    $data['event'] = '未知';
                    $data['device'] = '未知';
                }
            } elseif ($entry->getSrc() == CommissionBalance::ORDER_REFUND) {
                $data['event'] = '订单退款，返还佣金';
                $order_id = $entry->getExtraData('orderid');
                $order = Order::get($order_id);
                if ($order) {
                    $data['event'] .= "，订单号：{$order->getOrderNO()}";
                } else {
                    $data['event'] .= "，订单ID：$order_id";
                }
            } elseif ($entry->getSrc() == CommissionBalance::GSP) {
                $order_id = $entry->getExtraData('orderid');
                $order = Order::get($order_id);
                if ($order) {
                    $device = Device::get($order->getDeviceId());
                    $goods = Goods::data($order->getGoodsId());
                    if ($order->getPrice() > 0) {
                        $pay_type = User::getUserCharacter($order->getOpenid())['title'];
                        $spec = $pay_type . "：￥" . number_format($order->getPrice() / 100, 2) . "元 购买：" . $goods['name'] . "x" . $order->getNum();
                    } elseif ($order->getBalance() > 0) {
                        $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                        $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                        $spec = "使用" . $order->getBalance() . $unit_title . $balance_title . "购买：" . $goods['name'] . "x" . $order->getNum();
                    } else {
                        $spec = "免费领取：" . $goods['name'] . "x" . $order->getNum();
                    }
                    $account_name = $order->getAccount();
                    if ($account_name) {
                        $data['wx_account'] = $account_name;
                    }
                    $device_name = $device ? $device->getName() : '未知';
                    $data['event'] = $spec;
                    $data['device'] = $device_name;
                } else {
                    $data['event'] = '未知';
                    $data['device'] = '未知';
                }
            } elseif ($entry->getSrc() == CommissionBalance::BONUS) {
                $order_id = $entry->getExtraData('orderid');
                $order = Order::get($order_id);
                if ($order) {
                    $device = Device::get($order->getDeviceId());
                    $goods = Goods::data($order->getGoodsId());
                    if ($order->getPrice() > 0) {
                        $pay_type = User::getUserCharacter($order->getOpenid())['title'];
                        $spec = $pay_type . "：￥" . number_format($order->getPrice() / 100, 2) . "元 购买：" . $goods['name'] . "x" . $order->getNum();
                    } elseif ($order->getBalance() > 0) {
                        $balance_title = settings('user.balance.title', DEFAULT_BALANCE_TITLE);
                        $unit_title = settings('user.balance.unit', DEFAULT_BALANCE_UNIT_NAME);
                        $spec = "使用" . $order->getBalance() . $unit_title . $balance_title . "购买：" . $goods['name'] . "x" . $order->getNum();
                    } else {
                        $spec = "免费领取：" . $goods['name'] . "x" . $order->getNum();
                    }
                    $account = $order->getAccount(true);
                    if ($account) {
                        $data['wx_account'] = $account->getTitle();
                    } else {
                        $data['wx_account'] = $order->getAccount();
                    }
                    $device_name = $device ? $device->getName() : '未知';
                    $data['event'] = $spec;
                    $data['device'] = $device_name;
                } else {
                    $data['event'] = '未知';
                    $data['device'] = '未知';
                }
            } elseif ($entry->getSrc() == CommissionBalance::FEE) {
                $title = '';
                if ($entry->getExtraData('refund')) {
                    $title = '（已退回）';
                }
                $data['event'] = '提现手续费' . $title;
            } elseif ($entry->getSrc() == CommissionBalance::ADJUST) {
                $data['event'] = '管理员调整';
            }
            $logs[] = $data;
        }

        Util::exportExcel($file_name, $title_arr, $logs);
    }

    $title = '';
    $logs = [];
    $pager = '';
    if (!empty($user)) {
        $title = "<b>{$user->getName()}</b>的佣金记录";

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query = $user->getCommissionBalance()->log();
        $query->where($cond);

        $total = $query->count();
        $total_page = ceil($total / $page_size);

        if ($page > $total_page) {
            $page = 1;
        }

        if ($total > 0) {
            $pager = We7::pagination($total, $page, $page_size);
            $query->page($page, $page_size);
            $query->orderBy('createtime DESC');
            foreach ($query->findAll() as $entry) {
                $logs[] = CommissionBalance::format($entry);
            }
        }
    }
    $e_date->modify('-1 day');
    app()->showTemplate(
        'web/common/commission_export',
        [
            'title' => $title,
            'logs' => $logs,
            'pager' => $pager,
            's_keyword' => $s_keyword,
            's_date' => $s_date->format('Y-m-d'),
            'e_date' => $e_date->format('Y-m-d'),
            's_openid' => $s_openid,
            's_user_list' => $s_user_list,
        ]
    );
} elseif ($op == 'device_stats_view') {
    $agent_id = request::int('id');
    $agent = Agent::get($agent_id);

    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', '', 'error');
    }
    app()->showTemplate('web/agent/device_stats_view', [
        'agent' => $agent,
    ]);
} elseif ($op == 'commission_stats_view') {
    $agent_id = request::int('id');
    $agent = Agent::get($agent_id);

    if (empty($agent)) {
        Util::itoast('找不到这个代理商！', '', 'error');
    }
    app()->showTemplate('web/agent/commission_stats_view', [
        'agent' => $agent,
    ]);
} elseif ($op == 'device_order_statistics') {
    $agent_id = request::int('id');
    $agent = Agent::get($agent_id);

    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $month = '';
    if (request::has('month')) {
        $month_str = request::str('month');
        try {
            $month = new DateTimeImmutable($month_str);
        } catch (Exception $e) {
            JSON::fail('时间格式不正确！');
        }
        $fn = function ($device) use ($month) {
            return Statistics::deviceOrderMonth($device, $month);
        };
    } else {
        $start = request::str('start');
        $end = request::str('end');
        $fn = function ($device) use ($start, $end) {
            return Statistics::deviceOrder($device, $start, $end);
        };
    }

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = Device::query(['agent_id' => $agent->getId()]);

    $total = $query->count();
    $total_page = ceil($total / $page_size);
    if ($page > $total_page) {
        $page = 1;
    }

    $query->page($page, $page_size);

    $result = [
        'page' => $page,
        'totalpage' => $total_page,
        'list' => [],
    ];
    /** @var deviceModelObj $device */
    foreach ($query->findAll() as $device) {
        $result['list'][] = [
            'id' => $device->getId(),
            'uid' => $device->getUID(),
            'name' => $device->getName(),
            'stats' => $fn($device),
        ];
    }

    JSON::success($result);
} elseif ($op == 'year_commission_statistics') {
    $agent_id = request::int('id');
    $agent = Agent::get($agent_id);

    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $year_str = request::str('year');
    $month = request::int('month');

    $year = null;
    try {
        $year = new DateTimeImmutable(sprintf("%s-%02d-01", (new Datetime($year_str))->format('Y'), $month));
    } catch (Exception $e) {
        JSON::fail('时间格式不正确！');
    }

    if ($year->getTimestamp() > time()) {
        JSON::fail('时间不超过当前时间！');
    }

    $year_list = [];
    $first_order = Order::getFirstOrderOfAgent($agent);
    if ($first_order) {
        try {
            $begin = new DateTime(date('Y-m-d H:i:s', $first_order->getCreatetime()));
        } catch (Exception $e) {
            $begin = new DateTime();
        }
        $nextYear = new DateTime('first day of jan next year 00:00');
        while ($begin < $nextYear) {
            $year_list[] = $begin->format('Y');
            $begin->modify('next year');
        }
    } else {
        $year_list[] = (new DateTime())->format('Y');
    }

    $result = Statistics::userYear($agent, $year, $month);
    $result['title'] = $year->format('Y年');
    $result['year'] = $year_list;

    JSON::success($result);
} elseif ($op == 'month_commission_statistics') {
    $agent_id = request::int('id');
    $agent = Agent::get($agent_id);

    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    $month_str = request::str('month');
    $month = null;
    try {
        $month = new DateTimeImmutable($month_str);
    } catch (Exception $e) {
        JSON::fail('时间格式不正确！');
    }

    $result = Statistics::userMonth($agent, $month, request::int('day'));
    $result['title'] = $month->format('Y年m月');

    JSON::success($result);
}
