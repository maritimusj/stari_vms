<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Agent;
use zovye\domain\CommissionBalance;
use zovye\domain\Device;
use zovye\domain\GSP;
use zovye\domain\Keeper;
use zovye\domain\PaymentConfig;
use zovye\domain\Principal;
use zovye\domain\User;
use zovye\model\deviceModelObj;
use zovye\model\keeperModelObj;
use zovye\util\DBUtil;
use zovye\util\Helper;
use zovye\util\SQBUtil;
use zovye\util\Util;

$id = Request::int('id');
$from = Request::str('from');

$result = DBUtil::transactionDo(function () use ($id, &$from) {

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

    if (Request::bool('agent_base')) {

        $mobile = Request::trim('mobile');
        if (empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
            return err('手机号码无效！');
        }

        if (User::findOne(['mobile' => $mobile, 'id <>' => $user->getId(), 'app' => User::WX])) {
            return err('手机号码已经被其它用户使用！');
        }

        $name = Request::trim('name');
        $company = Request::trim('company');
        $license = Request::trim('license');
        $level = Request::trim('level');
        $area = array_intersect_key(Request::array('area'), ['province' => '省', 'city' => '市', 'district' => '区']);

        //上级代理
        $superior_data = [];

        $openid_s = Request::trim('superior');
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
                ],
                'funcs' => Helper::getAgentFNs(),
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

    } elseif (Request::has('agent_notice')) {

        if ($user->isAgent()) {
            $user->updateSettings(
                'agentData.notice',
                [
                    'device' => [
                        'online' => Request::bool('deviceOnline') ? 1 : 0,
                        'offline' => Request::bool('deviceOffline') ? 1 : 0,
                        'error' => Request::bool('deviceError') ? 1 : 0,
                        'low_battery' => Request::bool('deviceLowBattery') ? 1 : 0,
                        'low_remain' => Request::bool('deviceLowRemain') ? 1 : 0,
                    ],
                    'order' => [
                        'succeed' => Request::bool('orderSucceed') ? 1 : 0,
                        'failed' => Request::bool('orderFailed') ? 1 : 0,
                    ],
                ]
            );
        }
    } elseif (Request::has('agent_funcs')) {

        if ($user->isAgent()) {
            $data = Helper::parseAgentFNsFromGPC();
            $user->updateSettings('agentData.funcs', $data);

            if (App::isCustomWxAppEnabled()) {
                $user->updateSettings('agentData.wx.app', [
                    'key' => Request::trim('WxAppKey'),
                ]);
            }
        }
    } elseif (Request::has('agent_commission')) {

        if ($user->isAgent()) {

            $enabled = Request::bool('commission');
            $user->updateSettings('agentData.commission.enabled', $enabled);
            if ($enabled) {
                $user->updateSettings('agentData.commission.fee_type', Request::bool('feeType') ? 1 : 0);
                $user->updateSettings('agentData.commission.fee', intval(Request::float('commissionFee', 0, 2) * 100));
                if (Request::is_numeric('balanceOrderPrice')) {
                    $user->updateSettings(
                        'agentData.commission.balance.price',
                        intval(Request::float('balanceOrderPrice', 0, 2) * 100)
                    );
                } else {
                    $user->updateSettings('agentData.commission.balance', []);
                }
                $user->setPrincipal(Principal::Gspor);
            }

            //佣金分享
            $gsp_enabled = Request::bool('gsp_enabled');
            $user->updateSettings('agentData.gsp.enabled', $gsp_enabled);
            if ($gsp_enabled) {
                $gsp_mode = in_array(Request::str('gsp_mode'), [GSP::REL, GSP::FREE, GSP::MIXED], true) ?
                    Request::str('gsp_mode') : GSP::REL;
                $gsp_mode_type = Request::str('gsp_mode_type', GSP::PERCENT);
                $user->updateSettings('agentData.gsp.mode', $gsp_mode);
                $user->updateSettings('agentData.gsp.mode_type', $gsp_mode_type);

                if ($gsp_mode == GSP::REL) {
                    $user->updateSettings('agentData.gsp.order', [
                        'f' => Request::bool('freeOrderGSP') ? 1 : 0,
                        'b' => Request::bool('balanceOrderGSP') ? 1 : 0,
                        'p' => Request::bool('payOrderGSP') ? 1 : 0,
                    ]);

                    $rel_1 = (int)max(0, Request::float('rel_level1', 0, 2) * 100);
                    $rel_2 = (int)max(0, Request::float('rel_level2', 0, 2) * 100);
                    $rel_3 = (int)max(0, Request::float('rel_level3', 0, 2) * 100);

                    if (in_array($gsp_mode, [GSP::PERCENT, GSP::PERCENT_PER_GOODS], true)) {
                        $rel_1 = min(10000, $rel_1);
                        $rel_2 = min(10000, $rel_2);
                        $rel_3 = min(10000, $rel_3);
                    }

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
            $bonus_enabled = Request::bool('agentBonusEnabled');
            $user->updateSettings('agentData.bonus.enabled', $bonus_enabled);
            if ($bonus_enabled) {
                $user->updateSettings(
                    'agentData.bonus',
                    [
                        'enabled' => true,
                        'principal' => Request::trim('principal', CommissionBalance::PRINCIPAL_ORDER),
                        'order' => [
                            'f' => Request::bool('freeOrder') ? 1 : 0,
                            'b' => Request::bool('balanceOrder') ? 1 : 0,
                            'p' => Request::bool('payOrder') ? 1 : 0,
                        ],
                        'level0' => intval(Request::float('bonus_level0', 0, 2) * 100),
                        'level1' => intval(Request::float('bonus_level1', 0, 2) * 100),
                        'level2' => intval(Request::float('bonus_level2', 0, 2) * 100),
                        'level3' => intval(Request::float('bonus_level3', 0, 2) * 100),
                    ]
                );
            }
        }
    } elseif (Request::bool('agent_misc')) {

        if ($user->isAgent()) {
            $user->updateSettings(
                'agentData.misc',
                [
                    'maxTotalFree' => Request::int('maxTotalFree'),
                    'maxFree' => Request::int('maxFree'),
                    'maxAccounts' => Request::int('maxAccounts'),
                    'pushAccountMsg' => Request::trim('pushAccountMsg'),
                    'siteTitle' => Request::trim('siteTitle'),
                    'siteLogo' => Request::trim('image'),
                    'power' => Request::int('power'),
                    'auto_ref' => Request::int('auto_ref'),
                ]
            );

            $user->updateSettings(
                'agentData.device',
                [
                    'theme' => Request::str('theme'),
                    'remainWarning' => Request::int('remainWarning'),
                    'shipment' => [
                        'balanced' => Request::bool('shipmentBalance') ? 1 : 0,
                    ],
                ]
            );

            $locationEnabled = Request::bool('locationEnabled') ? 1 : 0;
            $user->updateSettings('agentData.location.validate.enabled', $locationEnabled);
            if ($locationEnabled) {
                $user->updateSettings('agentData.location.validate.distance', Request::int('locationDistance'));
            }

            if (App::isMustFollowAccountEnabled()) {
                $user->updateSettings('agentData.mfa', [
                    'enable' => Request::int('mustFollow'),
                ]);
            }

            $data = [
                'kind' => Request::int('kind'),
                'way' => Request::int('way'),
                'type' => Request::str('type', 'fixed'),
            ];
            
            if (App::isKeeperCommissionOrderDistinguishEnabled() && $data['way'] == Keeper::COMMISSION_ORDER) {
                $pay_commission_val = Request::float('payCommissionVal', 0, 2);
                $free_commission_val = Request::float('freeCommissionVal', 0, 2);
            
                if ($data['type'] == 'fixed') {
                    $data['pay_val'] = max(0, intval($pay_commission_val * 100));
                    $data['free_val'] = max(0, intval($free_commission_val * 100));
                } else {
                    $data['pay_val'] = max(0, min(10000, intval($pay_commission_val * 100)));
                    $data['free_val'] = max(0, min(10000, intval($free_commission_val * 100)));
                }
            } else {
                $commission_val = Request::float('commissionVal', 0, 2);
            
                if ($data['type'] == 'fixed') {
                    $data['val'] = max(0, intval($commission_val * 100));
                } else {
                    $data['val'] = max(0, min(10000, intval($commission_val * 100)));
                }
            }

            $user->updateSettings('agentData.keeper.data', $data);

            if (Request::bool('applyConfigToAll')) {
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

            if (App::isZeroBonusEnabled()) {
                $user->updateSettings('agentData.custom.bonus.zero.v', min(100, Request::float('zeroBonus', -1, 2)));
            }
        }
    } elseif (Request::bool('agent_payment')) {

        if (Request::bool('lcsw')) {
            if (!PaymentConfig::createOrUpdateFor($user->getId(), Pay::LCSW, [
                'merchant_no' => Request::trim('merchant_no'),
                'terminal_id' => Request::trim('terminal_id'),
                'access_token' => Request::trim('access_token'),
                'app' => [
                    'wx' => [
                        'h5' => Request::bool('lcswWxH5'),
                        'mini_app' => Request::bool('lcswWxMiniApp'),
                    ],
                    'ali' => Request::bool('lcswAli'),
                ]
            ])) {
                return err('保存扫呗配置失败！');
            }
        } else {
            PaymentConfig::remove([
                'agent_id' => $user->getId(),
                'name' => Pay::LCSW,
            ]);
        }

        if (Request::bool('SQB')) {
            if (Request::isset('app_id')) {
                $app_id = Request::trim('app_id');
                $vendor_sn = Request::trim('vendor_sn');
                $vendor_key = Request::trim('vendor_key');
                $code = Request::trim('code');
        
                $result = SQBUtil::activate($app_id, $vendor_sn, $vendor_key, $code);
    
                if (is_error($result)) {
                    Log::error('SQB', [
                        'app_id' => $app_id,
                        'vendor_sn' => $vendor_sn,
                        'vendor_key' => $vendor_key,
                        'code' => $code,
                        'error' => $result,
                    ]);
                } else {
                    PaymentConfig::createOrUpdateFor($user->getId(), Pay::SQB, [
                        'sn' => $result['terminal_sn'],
                        'key' => $result['terminal_key'],
                        'title' => $result['store_name'],
                        'app' => [
                            'wx' => [
                                'h5' => Request::bool('SQBWxH5'),
                                'mini_app' => Request::bool('SQBWxMiniApp'),
                            ],
                            'ali' => Request::bool('SQBAli'),
                        ]
                    ]);            
                }
            } else {
                $config = PaymentConfig::findOne([
                    'agent_id' => $user->getId(),
                    'name' => Pay::SQB,
                ]);
                if ($config) {
                    $config->setExtraData('app', [
                        'wx' => [
                            'h5' => Request::bool('SQBWxH5'),
                            'mini_app' => Request::bool('SQBWxMiniApp'),
                        ],
                        'ali' => Request::bool('SQBAli'),
                    ]);
                    $config->save();
                }
            }
        } else {
            PaymentConfig::remove([
                'agent_id' => $user->getId(),
                'name' => Pay::SQB,
            ]);
        }

        if (Request::bool('wx')) {
            if (!PaymentConfig::createOrUpdateFor($user->getId(), Pay::WX_V3, [
                'sub_mch_id' => Request::trim('wxMCHID'),
                'app' => [
                    'wx' => [
                        'h5' => true,
                        'mini_app' => true,
                    ],
                ],
            ])) {
                return err('保存微信支付配置失败！');
            }
        } else {
            PaymentConfig::remove([
                'agent_id' => $user->getId(),
                'name' => Pay::WX_V3,
            ]);
        }
    }

    if ($user->save()) {
        if ($is_edit) {
            return ['message' => '保存成功！'];
        }

        $from = 'edit';

        //启动通知任务
        Job::newAgent($user);

        return ['message' => '代理商设置成功！'];
    }

    return err('保存失败！');
});

Response::toast(
    $result['message'],
    Util::url('agent', ['op' => $from, 'id' => $id]),
    is_error($result) ? 'error' : 'success'
);
