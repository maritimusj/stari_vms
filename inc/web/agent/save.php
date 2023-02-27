<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\keeperModelObj;

$id = request::int('id');

$result = Util::transactionDo(function() use ($id) {

    $user = User::get($id);
    if (empty($user)) {
        return err('找不到这个用户！');
    }

    $is_edit = $user->isAgent();
    if (!$is_edit) {
        if ($user->isPartner()) {
            return err('用户已经是其他代理商的合伙人！');
        }

        if ($user->isKeeper()) {
            return err('用户已经是运营人员！');
        }
    }

    if (request::bool('agent_base')) {

        $mobile = request::trim('mobile');
        if (empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
            return err('手机号码无效！');
        }

        if (User::findOne(['mobile' => $mobile, 'id <>' => $user->getId(), 'app' => User::WX])) {
            return err('手机号码已经被其它用户使用！');
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
                return err('请选择正确的上级用户！');
            }

            if ($superior->getId() != $user->getSuperiorId()) {
                $user->setSuperiorId($superior->getId());
                $superior_data = [
                    'openid' => $superior->getOpenid(),
                    'name' => $superior->getNickname(),
                ];
            }
        } else {
            $user->setSuperiorId(0);
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
                    'order' => 1,
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
                        'distance' => App::getUserLocationValidateDistance(),
                    ],
                ],
                'commission' => [
                    'fee_type' => 1,
                ],
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
                    'order' => request::bool('orderNotify') ? 1 : 0,
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
                $gsp_mode = in_array(request::str('gsp_mode'), ['rel', 'free', 'mixed']) ? request::str(
                    'gsp_mode'
                ) : 'rel';
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
                        'principal' => request::trim('principal', CommissionBalance::PRINCIPAL_ORDER),
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
                    'auto_ref' => request::int('auto_ref'),
                ]
            );

            $user->updateSettings(
                'agentData.device',
                [
                    'theme' => request::str('theme'),
                    'remainWarning' => request::int('remainWarning'),
                    'shipment' => [
                        'balanced' => request::bool('shipmentBalance') ? 1 : 0,
                    ],
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

            $data = [
                'kind' => request::int('kind'),
                'way' => request::int('way'),
            ];

            $commission_val = request::float('commissionVal', 0, 2);
            $commission_type = request::str('type', 'fixed');

            if ($commission_type == 'fixed') {
                $data['fixed'] = max(0, intval($commission_val * 100));
                $data['type'] = 'fixed';
            } else {
                $data['percent'] = max(0, min(100, intval($commission_val)));
                $data['type'] = 'percent';
            }

            $user->updateSettings('agentData.keeper.data', $data);

            if (request::bool('applyConfigToAll')) {
                /** @var keeperModelObj $keeper */
                foreach (Keeper::query(['agent_id' => $user->getId()])->findAll() as $keeper) {
                    $query = Device::query(['keeper_id' => $keeper->getId()]);
                    /** @var deviceModelObj $device */
                    foreach ($query->findAll() as $device) {
                        if (!$device->setKeeper($keeper, $data)) {
                            return err('更新运营人员配置失败！');
                        }
                    }
                }
            }

            $user->updateSettings('agentData.keeper.reductGoodsNum', [
                'enabled' => request::int('reductGoodsNum'),
            ]);

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
                $data['wx']['mch_id'] = request::trim('wxMCHID');
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
        if ($is_edit) {
            return ['message' => '保存成功！'];
        }
        //使用控制中心推送通知
        Job::newAgent($user->getId());
        return ['message' => '代理商设置成功！'];
    }
    return err('保存失败！');
});

Util::itoast($result['message'], $this->createWebUrl('agent', ['op' => request::str('from'), 'id' => $id]), is_error($result) ? 'error' : 'success');
