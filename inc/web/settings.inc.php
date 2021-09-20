<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use DateTime;
use zovye\model\prizelistModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('device');

$settings = settings();

if ($op == 'save') {
    $url = _W('siteroot');
    Util::createApiRedirectFile('api.php', '', [
        'memo' => '这个文件是小程序请求转发程序!',
    ], function () use ($url) {
        return "
header(\"Access-Control-Allow-Origin: {$url}\");
header(\"Access-Control-Allow-Methods: GET,POST\");
header(\"Access-Control-Allow-Headers: Content-Type, LLT-API\");
header(\"Access-Control-Max-Age: 86400\");

if (isset(\$_SERVER['HTTP_LLT_API'])) {
    \$_GET['do'] = 'wxapi';
    \$_GET['vendor'] = strval(\$_SERVER['HTTP_LLT_API']);
} elseif (isset(\$_SERVER['HTTP_LLT_WXAPI'])) {
    \$_GET['do'] = 'wxxapi';
} else {
    exit('invalid request!');
}
";
    });

    $save_type = request::str('save_type');

    if ($save_type == 'device') {
        $settings['device']['autoJoin'] = request::bool('newDeviceAutoJoin') ? 1 : 0;
        $settings['device']['errorDown'] = request::bool('errorDown') ? 1 : 0;
        $settings['device']['clearErrorCode'] = request::bool('clearErrorCode') ? 1 : 0;
        $settings['device']['remainWarning'] = request::int('remainWarning');
        $settings['device']['waitTimeout'] = max(10, request::int('waitTimeout'));
        $settings['device']['lockRetries'] = request::int('lockRetries');
        $settings['device']['lockRetryDelay'] = request::int('lockRetryDelay');
        $settings['device']['lockTimeout'] = request::int('lockTimeout');

        $settings['device']['get'] = [
            'theme' => request::str('theme', 'default'),
        ];

        $settings['device']['lost'] = request::int('lost');
        $settings['device']['issuing'] = request::int('issuing');

        $settings['misc']['siteTitle'] = request::trim('siteTitle');
        $settings['misc']['siteCopyrights'] = request::trim('siteCopyrights');
        $settings['misc']['banner'] = request::trim('banner');
        $settings['misc']['siteWarning'] = request::trim('siteWarning');

        $settings['user']['location']['validate']['enabled'] = request::bool('userLocationEnabled') ? 1 : 0;

        if ($settings['user']['location']['validate']['enabled']) {
            $settings['user']['location']['validate']['distance'] = min(
                5000,
                max(1, request::int('userLocationDistance'))
            );
            $settings['user']['location']['validate']['expired'] = min(
                720,
                max(1, request::int('userLocationExpired'))
            );
        }

        $settings['user']['location']['appkey'] = request::trim('lbsKey', DEFAULT_LBS_KEY);

        $settings['device']['shipment']['balanced'] = request::bool('shipmentBalance') ? 1 : 0;

        $settings['goods']['agent']['edit'] = request::bool('allowAgentEditGoods') ? 1 : 0;

        $settings['goods']['image']['proxy'] = [
            'url' => request::trim('goodsImageProxyURL'),
            'secret' => request::trim('goodsImageProxySecret'),
        ];

        $settings['order']['rollback']['enabled'] = request::bool('autoRollbackOrder') ? 1 : 0;
        $settings['order']['goods']['maxNum'] = request::int('orderGoodsMaxNum');

        $settings['device']['v-device']['enabled'] = request::bool('vDevice') ? 1 : 0;
        $settings['device']['lac']['enabled'] = request::bool('lacConfirm') ? 1 : 0;

    } elseif ($save_type == 'user') {
        $settings['user']['center'] = [
            'enabled' => request::bool('usercenter') ? 1 : 0,
        ];

        if ($settings['user']['center']['enabled']) {
            $settings['user']['balance'] = [
                'title' => request::trim('balance_title') ?: DEFAULT_BALANCE_TITLE,
                'unit' => request::trim('balance_unit') ?: DEFAULT_BALANCE_UNIT_NAME,
                'price' => round(request('balance_price') * 100),
                'free' => request::int('balance_free'),
            ];
        }

        $settings['user']['prize']['enabled'] = $settings['user']['center']['enabled'] && request::bool('userprize') ? 1 : 0;
        if ($settings['user']['prize']['enabled']) {
            $settings['user']['prize']['maxtimes'] = max(1, request::int('maxTimes'));
        }

        $settings['user']['balance']['type'] = request::str('balance_type') == 'free' ? 'free' : 'pay';
        $settings['user']['verify']['enabled'] = request::bool('userVerify') ? 1 : 0;
        $settings['user']['verify']['maxtimes'] = max(1, request::int('maxtimes'));

        $settings['user']['verify18']['enabled'] = request::bool('userVerify18') ? 1 : 0;
        $settings['user']['verify18']['Title'] = request::trim('userVerify18Title');

    } elseif ($save_type == 'ctrl') {
        $url = request::trim('controlAddr');
        if (empty($url)) {
            $url = "http://127.0.0.1:8080";
        }
        if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = 'http://' . $url;
        }

        $settings['ctrl']['url'] = $url;
        $settings['ctrl']['appKey'] = request::trim('appKey');
        $settings['ctrl']['appSecret'] = request::trim('appSecret');
        $settings['ctrl']['checkSign'] = request::bool('checkSign') ? 1 : 0;

        if (empty($settings['ctrl']['signature'])) {
            $settings['ctrl']['signature'] = Util::random(32);
        }

        $settings['device']['v-device']['enabled'] = request::bool('vDevice') ? 1 : 0;
        $settings['goods']['lottery']['enabled'] = request::bool('lotteryGoods') ? 1 : 0;
        $settings['idcard']['verify']['enabled'] = request::bool('idCardVerify') ? 1 : 0;
        if (!$settings['idcard']['verify']['enabled']) {
            $settings['user']['verify']['enabled'] = 0;
        }

        $settings['device']['bluetooth']['enabled'] = request::bool('bluetoothDevice') ? 1 : 0;
        $settings['goods']['voucher']['enabled'] = request::bool('goodsVoucher') ? 1 : 0;

        $settings['custom']['mustFollow']['enabled'] = request::bool('mustFollow') ? 1 : 0;
        $settings['custom']['useAccountQRCode']['enabled'] = request::bool('useAccountQRCode') ? 1 : 0;
        $settings['custom']['aliTicket']['enabled'] = request::bool('aliTicket') ? 1 : 0;
        $settings['custom']['bonus']['zero']['enabled'] = request::bool('zeroBonus') ? 1 : 0;

        $settings['account']['wx']['platform']['enabled'] = request::bool('wxPlatform') ? 1 : 0;
        $settings['account']['douyin']['enabled'] = request::bool('douyin') ? 1 : 0;

        $specialAccounts = [
            'jfbFAN' => [
                __NAMESPACE__ . '\Account::createJFBAccount',
                'jfb.fan.enabled',
            ],

            'moscalesFAN' => [
                __NAMESPACE__ . '\Account::createMoscaleAccount',
                'moscale.fan.enabled',
            ],
            'yunfenbaFAN' => [
                __NAMESPACE__ . '\Account::createYunFenBaAccount',
                'yunfenba.fan.enabled',
            ],
            'ZJBaoFAN' => [
                __NAMESPACE__ . '\Account::createZJBaoAccount',
                'zjbao.fan.enabled',
            ],
            'AQiinfoFAN' => [
                __NAMESPACE__ . '\Account::createAQiinfoAccount',
                'AQiinfo.fan.enabled',
            ],
            'MeiPaFAN' => [
                __NAMESPACE__ . '\Account::createMeiPaAccount',
                'meipa.fan.enabled',
            ],
            'kingFAN' => [
                __NAMESPACE__ . '\Account::createKingFansAccount',
                'king.fan.enabled',
            ],
            'sntoFAN' => [
                __NAMESPACE__ . '\Account::createSNTOAccount',
                'snto.fan.enabled',
            ],
            'yfbFAN' => [
                __NAMESPACE__ . '\Account::createYFBAccount',
                'yfb.fan.enabled',
            ],
        ];

        $accounts_need_refresh = false;

        foreach ($specialAccounts as $key => $v) {
            $enabled = request::bool($key) ? 1 : 0;
            if ($enabled) {
                call_user_func($v[0]);
            }

            if (getArray($settings, $v[1]) != $enabled) {
                $accounts_need_refresh = true;
            }

            setArray($settings, $v[1], $enabled);
        }

        if ($accounts_need_refresh) {
            setArray($settings, 'accounts.lastupdate', '' . microtime(true));
        }

        $settings['custom']['channelPay']['enabled'] = request::bool('channelPay') ? 1 : 0;
        $settings['custom']['SQMPay']['enabled'] = request::bool('SQMPay') ? 1 : 0;
        $settings['custom']['DonatePay']['enabled'] = request::bool('DonatePay') ? 1 : 0;
        $settings['agent']['wx']['app']['enabled'] = request::bool('agentWxApp') ? 1 : 0;
        $settings['inventory']['enabled'] = request::bool('Inventory') ? 1 : 0;
        $settings['account']['appQRCode']['enabled'] = request::bool('AccountAppQRCode') ? 1 : 0;

        $settings['app']['first']['enabled'] = request::bool('ZovyeAppFirstEnable') ? 1 : 0;
        if ($settings['app']['first']['enabled']) {
            $module_url = str_replace('./', '/', $GLOBALS['_W']['siteroot'] . 'web' . we7::url('module/welcome/display', ['module_name' => APP_NAME, 'uniacid' => We7::uniacid()]));
            $files = [
                [
                    'filename' => IA_ROOT . '/index.php',
                    'content' => "<?php\r\nrequire './framework/bootstrap.inc.php';\r\nheader('Location: ' . '{$module_url}');\r\nexit();"
                ],
                [
                    'filename' => IA_ROOT . '/framework/bootstrap.inc.php',
                    'append' => true,
                    'content' => "\r\n\r\nif(\$action == 'login'){\r\n\t\$_GPC['referer'] = '{$module_url}';\r\n}"
                ],
            ];
            foreach ($files as $file) {
                $content = file_get_contents($file['filename']);
                if ($content && stripos($content, $module_url) === false) {
                    file_put_contents($file['filename'], $file['content'], $file['append'] ? FILE_APPEND : 0);
                }
            }
        }
    } elseif ($save_type == 'agent') {
        $settings['agentWxapp'] = [
            'key' => request::trim('WxAppKey'),
            'secret' => request::trim('WxAppSecret'),
        ];

        $settings['agent']['order']['refund'] = request::bool('allowAgentRefund') ? 1 : 0;

        if ($settings['inventory']['enabled']) {
            $settings['inventory']['goods']['mode'] = request::bool('inventoryGoodsLack') ? 1 : 0;
        }

        $settings['agent']['msg_tplid'] = request::trim('agentMsg');
        $settings['agent']['levels'] = [
            'level0' => ['clr' => request::trim('clr0'), 'title' => request::trim('level0')],
            'level1' => ['clr' => request::trim('clr1'), 'title' => request::trim('level1')],
            'level2' => ['clr' => request::trim('clr2'), 'title' => request::trim('level2')],
            'level3' => ['clr' => request::trim('clr3'), 'title' => request::trim('level3')],
            'level4' => ['clr' => request::trim('clr4'), 'title' => request::trim('level4')],
            'level5' => ['clr' => request::trim('clr5'), 'title' => request::trim('level5')],
        ];

        $settings['agent']['reg']['mode'] = request::bool('agentRegMode') ? Agent::REG_MODE_AUTO : Agent::REG_MODE_NORMAL;
        $settings['agent']['reg']['referral'] = request::bool('agentReferral') ? 1 : 0;

        if ($settings['agent']['reg']['mode'] == Agent::REG_MODE_AUTO) {

            $settings['agent']['reg']['superior'] = request::bool('YzshopSuperiorRelationship') ? 'yz' : 'n/a';
            $settings['agent']['reg']['level'] = request::str('agentRegLevel');
            $settings['agent']['reg']['commission_fee'] = round(request::float('agentCommissionFee', 0, 2) * 100);
            $settings['agent']['reg']['commission_fee_type'] = request::bool('feeType') ? 1 : 0;

            $settings['agent']['reg']['funcs'] = Util::parseAgentFNsFromGPC();

            if ($settings['commission']['enabled']) {
                //佣金分享
                $settings['agent']['reg']['rel_gsp']['enabled'] = request::bool('agentRelGsp') ? 1 : 0;
                $settings['agent']['reg']['gsp_mode_type'] = request::str('gsp_mode_type', 'percent');

                if ($settings['agent']['reg']['rel_gsp']['enabled']) {

                    $rel_0 = request::float('rel_gsp_level0', 0, 2) * 100;
                    $rel_1 = request::float('rel_gsp_level1', 0, 2) * 100;
                    $rel_2 = request::float('rel_gsp_level2', 0, 2) * 100;
                    $rel_3 = request::float('rel_gsp_level3', 0, 2) * 100;

                    if ($settings['agent']['reg']['gsp_mode_type'] == 'amount') {
                        $rel_0 = intval(round($rel_0));
                        $rel_1 = intval(round($rel_1));
                        $rel_2 = intval(round($rel_2));
                        $rel_3 = intval(round($rel_3));
                    } else {
                        $total = $rel_0 + $rel_1 + $rel_2 + $rel_3;
                        if ($total > 10000) {
                            $rel_3 = round($rel_3 / $total * 10000, 2);
                            $rel_2 = round($rel_2 / $total * 10000, 2);
                            $rel_1 = round($rel_1 / $total * 10000, 2);
                        }
                        $rel_0 = 10000 - $rel_1 - $rel_2 - $rel_3;
                    }

                    $settings['agent']['reg']['rel_gsp']['level0'] = $rel_0;
                    $settings['agent']['reg']['rel_gsp']['level1'] = $rel_1;
                    $settings['agent']['reg']['rel_gsp']['level2'] = $rel_2;
                    $settings['agent']['reg']['rel_gsp']['level3'] = $rel_3;

                    $settings['agent']['reg']['rel_gsp']['order'] = [
                        'f' => request::bool('freeOrderGSP') ? 1 : 0,
                        'b' => request::bool('balanceOrderGSP') ? 1 : 0,
                        'p' => request::bool('payOrderGSP') ? 1 : 0,
                    ];
                }
                //佣金奖励
                $settings['agent']['reg']['bonus']['enabled'] = request::bool('agentBonusEnabled') ? 1 : 0;
                if ($settings['agent']['reg']['bonus']['enabled']) {
                    $settings['agent']['reg']['bonus']['order'] = [
                        'f' => request::bool('freeOrder') ? 1 : 0,
                        'b' => request::bool('balanceOrder') ? 1 : 0,
                        'p' => request::bool('payOrder') ? 1 : 0,
                    ];

                    $settings['agent']['reg']['bonus']['level0'] = request::float('rel_bonus_level0', 0, 2) * 100;
                    $settings['agent']['reg']['bonus']['level1'] = request::float('rel_bonus_level1', 0, 2) * 100;
                    $settings['agent']['reg']['bonus']['level2'] = request::float('rel_bonus_level2', 0, 2) * 100;
                    $settings['agent']['reg']['bonus']['level3'] = request::float('rel_bonus_level3', 0, 2) * 100;
                }
            }
        }

        $settings['agent']['yzshop']['goods_limits']['enabled'] = request::bool('YzshopGoodsLimits') ? 1 : 0;

        if ($settings['agent']['yzshop']['goods_limits']['enabled']) {
            $goods_id = request::int('goodsID');
            if ($goods_id) {
                $settings['agent']['yzshop']['goods_limits']['id'] = $goods_id;
            }

            $settings['agent']['yzshop']['goods_limits']['OR'] = max(1, request::int('goodsOR'));
            $settings['agent']['yzshop']['goods_limits']['title'] = request::trim('restrictGoodsTitle');

            $settings['agent']['yzshop']['goods_limits']['order_status'] = [];
            if (request('goodsOrderState0')) {
                $settings['agent']['yzshop']['goods_limits']['order_status'][] = 0;
            }
            if (request('goodsOrderState1')) {
                $settings['agent']['yzshop']['goods_limits']['order_status'][] = 1;
            }
            if (request('goodsOrderState2')) {
                $settings['agent']['yzshop']['goods_limits']['order_status'][] = 2;
            }
            if (request('goodsOrderState3')) {
                $settings['agent']['yzshop']['goods_limits']['order_status'][] = 3;
            }
            if (request('goodsOrderStateN1')) {
                $settings['agent']['yzshop']['goods_limits']['order_status'][] = -1;
            }
        }

        Config::agent('agreement', [
            'agent' => [
                'enabled' => request::bool('agent_agreement'),
                'content' => request::trim('agent_agreement_content'),
            ],
            'keeper' => [
                'enabled' => request::bool('keeper_agreement'),
                'content' => request::trim('keeper_agreement_content'),
            ],
        ], true);

    } elseif ($save_type == 'commission') {
        $settings['commission']['enabled'] = request::bool('commission') ? 1 : 0;

        if ($settings['commission']['enabled']) {
            $settings['commission']['withdraw'] = [
                'times' => request::int('withdraw_times'),
                'min' => request::float('withdraw_min', 0, 2) * 100,
                'max' => request::float('withdraw_max', 0, 2) * 100,
                'count' => [
                    'month' => request::int('withdraw_maxcount'),
                ],
                'pay_type' => request::int('withdraw_pay_type'),
                'fee' => [
                    'permille' => min(1000, max(0, request::int('withdraw_fee_permille'))),
                    'min' => max(0, round(request::int('withdraw_fee_min') * 100)),
                    'max' => max(0, round(request::int('withdraw_fee_max') * 100)),
                ],
                'bank_card' => request::int('withdraw_bank_card'),
            ];

            if (request::isset('withdraw_fee_percent')) {
                $settings['commission']['withdraw']['fee']['percent'] = min(100, max(0, request::int('percent')));
            } else {
                $settings['commission']['withdraw']['fee']['percent'] = min(100, max(0, round(request::int('withdraw_fee_permille') / 10)));
            }
        }

        $settings['commission']['agreement']['freq'] = request::trim('commission_agreement');

        if ($settings['commission']['agreement']['freq']) {
            $settings['commission']['agreement']['content'] = request::str('commission_agreement_content');
            $settings['commission']['agreement']['version'] = sha1($settings['commission']['agreement']['content']);
        }

    } elseif ($save_type == 'advs') {

        if ($settings['custom']['SQMPay']['enabled']) {
            $settings['custom']['SQMPay']['appSecret'] = request::trim('appSecret');
            $settings['custom']['SQMPay']['js'] = request::str('js');

            $settings['custom']['SQMPay']['goodsNum'] = request::int('goodsNum', 1);
            if (empty($settings['custom']['SQMPay']['goodsNum'])) {
                $settings['custom']['SQMPay']['goodsNum'] = 1;
            }
            $settings['custom']['SQMPay']['bonus'] = max(0, request::float('bonus', 0, 2)) * 100;
        }

        if ($settings['custom']['aliTicket']['enabled']) {
            $settings['custom']['aliTicket']['key'] = request::trim('aliTicketAppKey');
            $settings['custom']['aliTicket']['secret'] = request::trim('aliTicketAppSecret');
            $settings['custom']['aliTicket']['goodsNum'] = request::int('aliTicketGoodsNum', 1);
            if (empty($settings['custom']['aliTicket']['goodsNum'])) {
                $settings['custom']['aliTicket']['goodsNum'] = 1;
            }
            $settings['custom']['aliTicket']['bonus'] = max(0, request::float('aliTicketBonus', 0, 2)) * 100;
            $settings['custom']['aliTicket']['title'] = request::trim('aliTicketTitle');
            $settings['custom']['aliTicket']['no'] = request::int('aliTicketNo');
        }

    } elseif ($save_type == 'account') {

        $settings['misc']['account']['priority'] = request::trim('accountPriority');

        if (App::isWxPlatformEnabled()) {
            $settings['account']['wx']['platform']['config']['appid'] = request::trim('wxPlatformAppID');
            $settings['account']['wx']['platform']['config']['secret'] = request::trim('wxPlatformAppSecret');
            $settings['account']['wx']['platform']['config']['token'] = request::trim('wxPlatformToken');
            $settings['account']['wx']['platform']['config']['key'] = request::trim('wxPlatformKey');
        }

        if (App::isDouyinEnabled()) {
            Config::douyin('client', [
                'key' => request::trim('douyinClientKey', ''),
                'secret' => request::trim('douyinClientSecret', ''),
            ], true);
        }

        $settings['account']['log']['enabled'] = request::bool('accountQueryLog') ? 1 : 0;

        $settings['misc']['adminAccount'] = request::trim('adminAccount');
        $settings['misc']['pushAccountMsg_type'] = request::trim('pushAccountMsg_type');
        $settings['misc']['pushAccountMsg_val'] = request::trim('pushAccountMsg_val');
        $settings['misc']['pushAccountMsg_delay'] = request::int('pushAccountMsg_delay');
        $settings['misc']['maxAccounts'] = request::int('maxAccounts');
        $settings['user']['maxFree'] = request::int('maxFree');
        $settings['misc']['accountsPromote'] = request::bool('accountsPromote') ? 1 : 0;

        $settings['order']['retry']['last'] = request::int('orderRetryLastTime');
        $settings['order']['retry']['max'] = request::int('orderRetryMaxCount');

        if (App::isMoscaleEnabled()) {
            $settings['moscale']['fan']['key'] = request::trim('moscaleMachineKey');
            $settings['moscale']['fan']['label'] = array_map(function ($e) {
                return intval($e);
            }, explode(',', request::trim('moscaleLabel')));
            $settings['moscale']['fan']['region'] = [
                'province' => request::int('province_code'),
                'city' => request::int('city_code'),
                'area' => request::int('area_code'),
            ];
        }

    } elseif ($save_type == 'notice') {
        $settings['notice'] = [
            'sms' => [
                'url' => 'https://v.juhe.cn/sms/send?',
                'appkey' => request::trim('smsAppkey'),
                'verify' => request::trim('smsVerify'),
            ],
            'reload_smstplid' => request::trim('reloadSMSTplid'),
            'reload_tplid' => request::trim('reload_tplid'),
            'agentReq_tplid' => request::trim('agentReqTplid'),
            'deviceerr_tplid' => request::trim('deviceErrorTplid'),
            'deviceOnline_tplid' => request::trim('deviceOnlineTplid'),
            'agentresult_tplid' => request::trim('agentResultTplid'),
            'withdraw_tplid' => request::trim('withdrawTplid'),
            'advReviewTplid' => request::trim('advReviewTplid'),
            'advReviewResultTplid' => request::trim('advReviewResultTplid'),
            'delay' => [
                'remainWarning' => request::int('remainWarningDelay') ?: 1,
                'deviceerr' => request::int('deviceErrorDelay') ?: 1,
                'deviceOnline' => request::int('deviceOnlineDelay') ?: 1,
            ],
        ];

        $reviewAdminUserId = request::int('reviewAdminUser');

        if ($reviewAdminUserId) {
            $user = User::get($reviewAdminUserId);
            if ($user) {
                $settings['notice']['reviewAdminUserId'] = $reviewAdminUserId;
            }
        }

        $authorizedAdminUserId = request::int('authorizedAdminUser');

        if ($authorizedAdminUserId) {
            $user = User::get($authorizedAdminUserId);
            if ($user) {
                $settings['notice']['authorizedAdminUserId'] = $authorizedAdminUserId;
            }
        }

        $withdrawAdminUserId = request::int('withdrawAdminUser');

        if ($withdrawAdminUserId) {
            $user = User::get($withdrawAdminUserId);
            if ($user) {
                $settings['notice']['withdrawAdminUserId'] = $withdrawAdminUserId;
            }
        }

    } elseif ($save_type == 'misc') {
        $settings['misc']['redirect'] = [
            'success' => [
                'url' => request::trim('success_url'),
            ],
            'fail' => [
                'url' => request::trim('fail_url'),
            ],
        ];

        $settings['we7credit']['enabled'] = request::bool('we7credit') ? 1 : 0;

        if ($settings['we7credit']['enabled']) {

            $settings['we7credit']['type'] = request::trim('credit_type');
            $settings['we7credit']['val'] = request::int('credit_val');
            $settings['we7credit']['require'] = request::int('credit_require');
        }

        $settings['advs']['assign'] = [
            'multi' => request::bool('advsAssignMultilMode') ? 1 : 0,
        ];

        $settings['misc']['qrcode']['default_url'] = request::trim('default_url');
        $settings['user']['discountPrice'] = request::float('discountPrice', 0, 2) * 100;

        if (App::isMustFollowAccountEnabled()) {
            $settings['mfa'] = [
                'enable' => request::bool('mustFollow') ? 1 : 0,
            ];
        }

        $settings['api'] = [
            'account' => request::trim('account'),
        ];

        if (App::isDonatePayEnabled()) {
            Config::donatePay('qsc', [
                'title' => request::trim('donatePayTitle'),
                'desc' => request::trim('donatePayDesc'),
                'url' => request::trim('donatePayUrl'),
            ], true);
        }

        if (App::isZeroBonusEnabled()) {
            $settings['custom']['bonus']['zero']['v'] = min(100, request::float('zeroBonus', 0, 2));
        }
    } elseif ($save_type == 'payment') {
        $wx_enabled = request::bool('wx') ? 1 : 0;
        $settings['pay']['wx']['enable'] = $wx_enabled;

        if ($wx_enabled) {
            $settings['pay']['wx']['appid'] = request::trim('wxAppID');
            $settings['pay']['wx']['wxappid'] = request::trim('wxxAppID');
            $settings['pay']['wx']['key'] = request::trim('wxAppKey');
            $settings['pay']['wx']['mch_id'] = request::trim('wxMCHID');
            $settings['pay']['wx']['pem'] = [
                'cert' => request::trim('certPEM'),
                'key' => request::trim('keyPEM'),
            ];

            if (false === Util::createApiRedirectFile('payment/wx.php', 'payresult', [
                    'headers' => [
                        'HTTP_USER_AGENT' => 'wx_notify',
                    ],
                    'op' => 'notify',
                    'from' => 'wx',
                ])) {
                Util::itoast('创建微信支付入口文件失败！');
            }
        }

        $lcsw_enabled = request::bool('lcsw') ? 1 : 0;
        $settings['pay']['lcsw']['enable'] = $lcsw_enabled;

        if ($lcsw_enabled) {
            $settings['pay']['lcsw']['merchant_no'] = request::trim('merchant_no');
            $settings['pay']['lcsw']['terminal_id'] = request::trim('terminal_id');
            $settings['pay']['lcsw']['access_token'] = request::trim('access_token');

            if (false === Util::createApiRedirectFile('payment/lcsw.php', 'payresult', [
                    'headers' => [
                        'HTTP_USER_AGENT' => 'lcsw_notify',
                    ],
                    'op' => 'notify',
                    'from' => 'lcsw',
                ])) {
                Util::itoast('创建扫呗支付入口文件失败！');
            }
        }

        $settings['ali']['appid'] = request::trim('ali_appid');
        $settings['ali']['pubkey'] = request::trim('ali_pubkey');
        $settings['ali']['prikey'] = request::trim('ali_prikey');

        $settings['alixapp']['id'] = request::trim('alixapp_id');
        $settings['alixapp']['pubkey'] = request::trim('alixapp_pubkey');
        $settings['alixapp']['prikey'] = request::trim('alixapp_prikey');

        $settings['pay']['channel'] = [
            'key' => request::trim('channelPayKey'),
            'secret' => request::trim('channelPaySecret'),
        ];

        if (!isEmptyArray($settings['pay']['channel'])) {
            if (false === Util::createApiRedirectFile('payment/channel.php', 'payresult', [
                    'headers' => [
                        'HTTP_USER_AGENT' => 'channel_notify',
                    ],
                    'op' => 'notify',
                    'from' => 'channel',
                ])) {
                Util::itoast('创建阿旗（京东）支付入口文件失败！');
            }
        }

        if ($settings['SQB']['enable']) {
            Util::createApiRedirectFile('/payment/SQB.php', 'payresult', [
                'headers' => [
                    'HTTP_USER_AGENT' => 'SQB_notify',
                ],
                'op' => 'notify',
                'from' => 'SQB',
            ]);
        }

    } elseif ($save_type == 'data_view') {
        $db_arr = [];
        $res = m('data_view')->findAll();
        foreach ($res as $item) {
            $db_arr[$item->getK()] = $item->getV();
        }

        $template_keys = [
            'title',
            'total_sale_init', 'total_sale_freq', 'total_sale_section1', 'total_sale_section2',
            'today_sale_init', 'today_sale_freq', 'today_sale_section1', 'today_sale_section2',
            'total_order_init', 'total_order_freq', 'total_order_section1', 'total_order_section2',
            'today_order_init', 'today_order_freq', 'today_order_section1', 'today_order_section2',
            'user_man', 'user_woman',
            'income_wx', 'income_ali',
            'g1', 'g2', 'g3', 'g4', 'g5', 'g6', 'g7', 'g8', 'g9', 'g10',
            'p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9', 'p10', 'p11', 'p12', 'p13', 'p14', 'p15', 'p16', 'p17', 'p18', 'p19',
            'p20', 'p21', 'p22', 'p23', 'p24', 'p25', 'p26', 'p27', 'p28', 'p29', 'p30', 'p31',
        ];

        $req_arr = [];

        foreach ($template_keys as $val) {
            if (request::isset($val)) {
                $req_arr[$val] = request($val);
            }
        }

        $intersection_arr = array_intersect_assoc($db_arr, $req_arr);
        $need_inserted_arr = array_diff_assoc($req_arr, $intersection_arr);

        foreach ($res as $item) {
            if (isset($need_inserted_arr[$item->getK()])) {
                $item->setV($need_inserted_arr[$item->getK()]);
                $item->setCreatetime(time());
                $item->save();
                unset($need_inserted_arr[$item->getK()]);
            }
        }

        $query = m('data_view');
        foreach ($need_inserted_arr as $key => $val) {
            $query->create(['k' => $key, 'v' => $val, 'createtime' => time()]);
        }
    }

    if (app()->saveSettings($settings)) {
        Util::itoast('设置保存成功！', $this->createWebUrl('settings', ['op' => $save_type]), 'success');
    }

    Util::itoast('设置保存失败！', $this->createWebUrl('settings', ['op' => $save_type]), 'error');
}

/**
 * 初始化设置页面数据
 */
$tpl_data['navs'] = [
    'device' => '设备',
    'user' => '用户',
    'agent' => '代理商',
    'wxapp' => '小程序',
    'commission' => '佣金',
    'advs' => '广告',
    'account' => '公众号',
    'notice' => '通知',
    'payment' => '支付',
    'misc' => '其它',
    'upgrade' => '系统升级',
];

if (!$settings['custom']['SQMPay']['enabled']) {
    unset($tpl_data['navs']['advs']);
}

if (!App::isCustomWxAppEnabled()) {
    unset($tpl_data['navs']['wxapp']);
}

if ($op == 'account') {
    if (App::isWxPlatformEnabled()) {
        if (empty($settings['account']['wx']['platform']['config']['token']) || empty($settings['account']['wx']['platform']['config']['key'])) {

            $settings['account']['wx']['platform']['config']['token'] = Util::random(32);
            $settings['account']['wx']['platform']['config']['key'] = Util::random(43);

            updateSettings('account.wx.platform.config', $settings['account']['wx']['platform']['config']);
        }

        $tpl_data['auth_notify_url'] = Util::murl('wxplatform', ['op' => WxPlatform::AUTH_NOTIFY]);
        $tpl_data['msg_notify_url'] = Util::murl('wxplatform', ['op' => WxPlatform::AUTHORIZER_EVENT]) . '&appid=/$APPID$';
    }

    if (App::isMoscaleEnabled()) {
        $tpl_data['moscaleMachineKey'] = strval($settings['moscale']['fan']['key']);
        $tpl_data['moscaleLabelList'] = MoscaleAccount::getLabelList();
        $tpl_data['moscaleAreaListSaved'] = is_array($settings['moscale']['fan']['label']) ? $settings['moscale']['fan']['label'] : [];

        $tpl_data['moscaleRegionData'] = MoscaleAccount::getRegionData();
        $tpl_data['moscaleRegionSaved'] = is_array($settings['moscale']['fan']['region']) ? $settings['moscale']['fan']['region'] : [];
    }

    if (App::isDouyinEnabled()) {
        $tpl_data['douyin'] = Config::douyin('client', []);
    }

} elseif ($op == 'refreshWxPlatformToken') {

    JSON::success([
        'token' => Util::random(32),
    ]);

} elseif ($op == 'refreshWxPlatformKey') {

    JSON::success([
        'key' => Util::random(43),
    ]);

} elseif ($op == 'ctrl') {

    $tpl_data['is_locked'] = app()->isLocked();
    $tpl_data['cb_url'] = Util::getCtrlServCallbackUrl();
    $tpl_data['navs']['ctrl'] = '高级设置';

    $res = CtrlServ::query();
    if (!is_error($res)) {
        $data = empty($res['data']) ? $res : $res['data'];

        $tpl_data['version'] = $data['version'] ?: 'n/a';
        $tpl_data['build'] = $data['build'] ?: 'n/a';

        if ($data['start']) {
            $tpl_data['formatted_duration'] = Util::getFormattedPeriod($data['start']);
        } else if ($data['startTime']) {
            $tpl_data['formatted_duration'] = Util::getFormattedPeriod($data['startTime']);
        }

        if ($data['now']) {
            $tpl_data['formatted_now'] = (new DateTime())->setTimestamp($data['now'])->format("Y-m-d H:i:s");
        }
        $tpl_data['queue'] = Config::app('queue', []);
    }
    $tpl_data['migrate'] = Migrate::detect(false);
} elseif ($op == 'unlock') {

    app()->resetLock();
    JSON::success('成功！');

} elseif ($op == 'migrate') {

    if (Migrate::detect(false)) {
        JSON::success(['redirect' => Util::url('migrate')]);
    }

} elseif ($op == 'reset') {

    Migrate::reset();
    if (Migrate::detect(false)) {
        JSON::success(['redirect' => Util::url('migrate')]);
    }
    JSON::success('已重置！');

} elseif ($op == 'device') {

    $tpl_data['lbsKey'] = settings('user.location.appkey', DEFAULT_LBS_KEY);
    $tpl_data['loc_url'] = Util::murl('util');
    $tpl_data['test_url'] = Util::murl('testing');
    $tpl_data['get_schema'] = settings('device.get.theme');
    $tpl_data['themes'] = Theme::all();
    $tpl_data['lbs_limits'] = Config::location('tencent.lbs.limits', []);

} elseif ($op == 'agent') {

    $tpl_data['mobile_url'] = Util::murl('mobile');

    if (YZShop::isInstalled()) {
        $goods = YZShop::getGoodsList();
        $exists = false;
        foreach ($goods as &$entry) {
            if ($settings['agent']['yzshop']['goods_limits']['id'] == $entry['id']) {
                $entry['selected'] = true;
                $exists = true;
            }
        }

        if (!$exists) {
            $goods[] = [
                'id' => 0,
                'title' => '<找不到指定的商品，请重新选择>',
                'selected' => true,
            ];
        }
    }

    $tpl_data['agreement'] = Config::agent('agreement', []);
    //var_dump($tpl_data);exit();

} elseif ($op == 'wxapp') {
    $query = WxApp::query();

    $keyword = request::trim('keyword');
    if (!empty($keywords)) {
        $query->where([
            'name REGEXP' => $keyword,
            'key REGEXP' => $keyword,
        ]);
        $tpl_data['s_keyword'] = $keyword;
    }

    $total = $query->count();

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    if ($page > ceil($total / $page_size)) {
        $page = 1;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $list = [];
    foreach ($query->findAll() as $wxapp) {
        $data = [
            'id' => $wxapp->getId(),
            'name' => $wxapp->getName(),
            'key' => $wxapp->getKey(),
            'secret' => $wxapp->getSecret(),
            'createtime_formatted' => date('Y-m-d H:i:s', $wxapp->getCreatetime()),
        ];
        $list[] = $data;
    }

    $tpl_data['list'] = $list;

} elseif ($op == 'user') {

    $settings['user']['balance']['price'] = number_format($settings['user']['balance']['price'] / 100, 2);

    $tpl_data['usercenter_url'] = Util::murl('usercenter');
    $tpl_data['prizes'] = Prize::all();

    $prizeEntries = [];
    $query = m('prizelist')->where(We7::uniacid([]));

    $tpl_data['total'] = intval($query->get('sum(percent)'));
    $max = 0;

    /** @var prizelistModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'enabled' => $entry->getEnabled(),
            'extra' => unserialize($entry->getExtra()) ?: [],
            'name' => $entry->getName(),
            'title' => $entry->getTitle(),
            'percent' => $entry->getPercent(),
            'total' => $entry->getTotal(),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        if ($data['enabled']) {

            $remain = $data['extra']['maxcount'] == 0 || $data['total'] < $data['extra']['maxcount'];
            $begin = empty($data['extra']['begin']) || time() >= strtotime($data['extra']['begin']);
            $end = empty($data['extra']['end']) || time() < strtotime($data['extra']['end']) + 24 * 60 * 60;

            if ($remain && $begin && $end) {
                $max += $data['percent'];
            }
        }

        $prizeEntries[] = $data;
    }

    $tpl_data['prizeEntries'] = $prizeEntries;

    $max = min(100, $max);
    foreach ($prizeEntries as &$entry) {

        if ($entry['enabled']) {
            $remain = $entry['extra']['maxcount'] == 0 || ($entry['extra']['maxcount'] > 0 && $entry['total'] < $entry['extra']['maxcount']);
            $begin = empty($entry['extra']['begin']) || time() >= strtotime($entry['extra']['begin']);
            $end = empty($entry['extra']['end']) || time() < strtotime($entry['extra']['end']) + 24 * 60 * 60;

            if ($remain && $begin && $end) {
                $entry['pv'] = round($entry['percent'] / $max * 100, 2);
            } else {
                $entry['pv'] = 0;
            }

            if (!$remain) {
                $entry['invalid'] = '数量已满';
            }

            if (!($begin && $end)) {
                $entry['invalid'] = '不在有效期';
            }
        } else {
            $entry['pv'] = 0;
            $entry['invalid'] = '已禁用';
        }
    }

    $res = CtrlServ::v2_query('idcard/balance');
    $tpl_data['idcard_balance'] = 0;

    if (!empty($res) && $res['status']) {
        $tpl_data['idcard_balance'] = $res['data']['balance'];
    } else {
        $tpl_data['idcard_balance'] = $res['data']['msg'];
    }

} elseif ($op == 'advs') {

    if ($settings['custom']['SQMPay']['enabled']) {
        $tpl_data['cbURL'] = SQM::getCallbackUrl();
    }

    $tpl_data['aliTicketURL'] = AliTicket::getCallbackUrl();

} elseif ($op == 'notice') {

    if ($settings['notice']['reviewAdminUserId']) {
        $user = User::get($settings['notice']['reviewAdminUserId']);
        if ($user) {
            $settings['notice']['reviewAdminUser'] = ['id' => $user->getId(), 'nickname' => $user->getName()];
        }
    }

    if ($settings['notice']['authorizedAdminUserId']) {
        $user = User::get($settings['notice']['authorizedAdminUserId']);
        if ($user) {
            $settings['notice']['authorizedAdminUser'] = ['id' => $user->getId(), 'nickname' => $user->getName()];
        }
    }

    if ($settings['notice']['withdrawAdminUserId']) {
        $user = User::get($settings['notice']['withdrawAdminUserId']);
        if ($user) {
            $settings['notice']['withdrawAdminUser'] = ['id' => $user->getId(), 'nickname' => $user->getName()];
        }
    }

} elseif ($op == 'commission') {

    $tpl_data['pem'] = empty($settings['pem']) ? ['key' => '', 'cert' => ''] : unserialize($settings['pem']);

    if (!isset($settings['commission']['withdraw']['fee']['permille'])) {
        $settings['commission']['withdraw']['fee']['permille'] = min(1000, intval($settings['commission']['withdraw']['fee']['percent'] * 10));
    }

    $settings['commission']['withdraw']['min'] = $settings['commission']['withdraw']['min'] / 100;
    $settings['commission']['withdraw']['max'] = $settings['commission']['withdraw']['max'] / 100;

    $tpl_data['withdraw_url'] = Util::murl('withdraw');

} elseif ($op == 'payment') {

    $channel_cb_url = _W('siteroot');
    $path = 'addons/' . APP_NAME . '/';

    if (mb_strpos($channel_cb_url, $path) === false) {
        $channel_cb_url .= $path;
    }

    $tpl_data['channel_cb_url'] = $channel_cb_url . 'payment/channel.php';
    $tpl_data['channel_forward_url'] = Util::murl('channel', ['op' => 'result']);

} elseif ($op == 'misc') {

    $tpl_data['media'] = ['type' => settings('misc.pushAccountMsg_type'), 'val' => settings('misc.pushAccountMsg_val')];
    We7::load()->model('mc');
    $tpl_data['credit_types'] = We7::mc_credit_types();

    $tpl_data['api_url'] = Util::murl('api');
    $app_key = settings('app.key');
    if (empty($app_key)) {
        $app_key = Util::random(16);
        updateSettings('app.key', $app_key);
    }
    $tpl_data['app_key'] = $app_key;
    $tpl_data['account'] = settings('api.account', '');
    if (App::isDonatePayEnabled()) {
        $tpl_data['donatePay'] = Config::donatePay('qsc');
    }
} elseif ($op == 'accountMsgConfig') {

    $media = request('media') ?: [
        'type' => settings('misc.pushAccountMsg_type'),
        'val' => settings('misc.pushAccountMsg_val'),
    ];

    $typename = request::trim('typename');

    $res = Util::getWe7Material($typename, request('page'), request('pagesize'));

    $content = app()->fetchTemplate(
        'web/account/msg',
        [
            'typename' => $typename,
            'media' => $media,
            'list' => $res['list'],
        ]
    );

    JSON::success([
        'title' => $res['title'],
        'content' => $content,
    ]);

} elseif ($op == 'prizes') {

    $content = app()->fetchTemplate(
        'web/prize/list',
        [
            'entries' => Prize::all(),
        ]
    );

    JSON::success([
        'title' => '请选择',
        'content' => $content,
    ]);

} elseif ($op == 'banPrize') {

    $id = request::int('id');
    if ($id) {
        /** @var prizelistModelObj $prize */
        $prize = m('prizelist')->findOne(We7::uniacid(['id' => $id]));
        if ($prize) {
            $state = $prize->getEnabled();
            $prize->setEnabled($state ? 0 : 1);
            if ($prize->save()) {
                if ($prize->getEnabled()) {
                    $msg = '已启用！';
                    $tips = '';
                    $extra = unserialize($prize->getExtra());
                    if ($extra['maxcount'] > 0 && $prize->getTotal() >= $extra['maxcount']) {
                        $tips = '(已失效，数量已满)';
                    } elseif (!empty($extra['begin']) && time() >= strtotime($extra['begin'])) {
                        $tips = '(已失效，不在有效期)';
                    } elseif (!empty($extra['end']) && time() < strtotime($extra['end']) + 24 * 60 * 60) {
                        $tips = '(已失效，不在有效期)';
                    }
                } else {
                    $msg = '已禁用！';
                    $tips = '(已失效，已禁用)';
                }

                JSON::success(['msg' => $msg, 'tips' => $tips, 'enabled' => $prize->getEnabled()]);
            }
        }
    }

    JSON::fail('失败!');

} elseif ($op == 'savePrize') {

    $typename = request::trim('type');
    $params = [];
    parse_str(request('params'), $params);

    $percent = min(100, max(1, intval($params['percent'])));
    $title = trim($params['title']);

    $begin = 0;
    $end = 0;

    if ($params['validate']) {
        $begin = strtotime($params['begin']);
        $end = strtotime($params['end']) + (24 * 60 * 60 - 1);
    } else {
        $params['begin'] = 0;
        $params['end'] = 0;
    }

    $params['maxcount'] = intval($params['maxcount']);

    if (empty($title)) {
        JSON::fail('请指定奖品名称！');
    }

    $entries = Prize::all();

    if (isset($entries[$typename])) {
        $res = $entries[$typename]->isValid($params);
        if (is_error($res)) {
            JSON::fail($res);
        }

        $id = intval($params['id']);
        if ($id) {
            /** @var prizelistModelObj $p */
            $p = m('prizelist')->findOne(We7::uniacid(['id' => $id]));
            if ($p) {
                $p->setTitle($title);
                $p->setMaxCount($params['maxcount']);
                $p->setBeginTime($begin);
                $p->setEndTime($end);
                $p->setPercent($percent);
                $p->setExtra(serialize($params));
                if (!$p->save()) {
                    JSON::fail('保存失败！');
                }
            } else {
                JSON::fail('找不到这个奖品！');
            }
        } else {
            $p = m('prizelist')->create(
                We7::uniacid(
                    [
                        'name' => $typename,
                        'title' => $title,
                        'enabled' => 1,
                        'max_count' => $params['maxcount'],
                        'begin_time' => $begin,
                        'end_time' => $end,
                        'percent' => $percent,
                        'extra' => serialize($params),
                    ]
                )
            );

            if (empty($p)) {
                JSON::fail('创建奖品失败！');
            }
        }

        updateSettings('user.prize.enabled', 1);

        Util::resultJSON($p ? true : false, ['msg' => $p ? "{$p->getTitle()}保存成功！" : '保存失败！']);
    }

    JSON::fail("无法创建{$title}");

} elseif ($op == 'removePrize') {

    $id = request::int('id');
    $prize = m('prizelist')->findOne(We7::uniacid(['id' => $id]));
    if ($prize) {
        $prize->destroy();

        updateSettings('user.prize.enabled', 1);

        JSON::success();
    }

    JSON::fail();

} elseif ($op == 'addPrize') {

    $type = request::trim('type');
    $id = request::int('id');

    $prize_data = [];
    if ($id) {
        /** @var prizelistModelObj $prize */
        $prize = m('prizelist')->findOne(We7::uniacid(['id' => $id]));
        if ($prize) {
            $prize_data = unserialize($prize->getExtra()) ?: [];
            $prize_data['id'] = $prize->getId();
            $prize_data['maxcount'] = $prize->getMaxCount();
            $prize_data['begin'] = $prize->getBeginTime();
            $prize_data['end'] = $prize->getEndTime();
        }
    }

    $types = [
        'voucher' => ['title' => $settings['user']['balance']['title'] ?: '点券'],
        'coupon' => ['title' => '代金券'],
        'other' => ['title' => '其它奖励'],
    ];

    if (array_key_exists($type, $types)) {
        $content = app()->fetchTemplate(
            "web/prize/prize-{$type}",
            [
                'id' => $id,
                'prizeData' => $prize_data,
            ]
        );

        JSON::success([
            'title' => $types[$type]['title'],
            'content' => $content,
        ]);
    }

    JSON::fail('找不到这个类型的奖品！');

} elseif ($op == 'enableSQB') {

    $app_id = request::trim('app_id');
    $vendor_sn = request::trim('vendor_sn');
    $vendor_key = request::trim('vendor_key');
    $code = request::trim('code');

    $result = SQB::activate($app_id, $vendor_sn, $vendor_key, $code);

    if (is_error($result)) {
        JSON::fail($result);
    }

    if (false === Util::createApiRedirectFile('/payment/SQB.php', 'payresult', [
            'headers' => [
                'HTTP_USER_AGENT' => 'SQB_notify',
            ],
            'op' => 'notify',
            'from' => 'SQB',
        ])) {
        Util::itoast('创建收钱吧支付入口文件失败！');
    }

    if (updateSettings('pay.SQB', [
        'enable' => 1,
        'sn' => $result['terminal_sn'],
        'key' => $result['terminal_key'],
        'title' => $result['store_name']
    ])) {
        JSON::success('成功！');
    }

    JSON::success('失败！');

} elseif ($op == 'disableSQB') {

    if (updateSettings('pay.SQB', [])) {
        JSON::success('成功！');
    }

    JSON::fail('失败！');

} elseif ($op == 'data_view') {

    $tpl_data['navs']['data_view'] = '数据大屏';

    $goods = [
        'g1' => '商品一', 'g2' => '商品二', 'g3' => '商品三',
        'g4' => '商品四', 'g5' => '商品五', 'g6' => '商品六',
        'g7' => '商品七', 'g8' => '商品八', 'g9' => '商品九',
        'g10' => '商品十'
    ];

    $provinces = Util::getProvinceList();

    $tpl_data['goods'] = $goods;
    $tpl_data['provinces'] = $provinces;

    $keys = [
        'title',
        'total_sale_init', 'total_sale_freq', 'total_sale_section1', 'total_sale_section2',
        'today_sale_init', 'today_sale_freq', 'today_sale_section1', 'today_sale_section2',
        'total_order_init', 'total_order_freq', 'total_order_section1', 'total_order_section2',
        'today_order_init', 'today_order_freq', 'today_order_section1', 'today_order_section2',
        'user_man', 'user_woman',
        'income_wx', 'income_ali',
    ];

    $keys = array_merge($keys, array_keys($goods), array_keys($provinces));

    $values = [];
    $diff = [];

    $res = m('data_view')->findAll();

    foreach ($res as $item) {
        if (in_array($item->getK(), $keys)) {
            $values[$item->getK()] = $item->getV();
            $diff[] = $item->getK();
        }
    }

    $left_keys = array_diff($keys, $diff);
    /** @var string $key */
    foreach ($left_keys as $key) {
        $values[$key] = '';
    }

    $tpl_data = array_merge($tpl_data, $values);

    $dm = Util::murl('app', ['op' => 'data_view']);

    $tpl_data['dm'] = $dm;

} elseif ($op == 'upgrade') {

    $tpl_data['upgrade'] = [];
    $back_url = $this->createWebUrl('settings', ['op' => 'upgrade']);

    $data = Util::get(UPGRADE_URL);
    if (empty($data)) {
        $tpl_data['upgrade']['error'] = '检查更新失败！';
    } else {
        $res = json_decode($data, true);
        if ($res) {
            if ($res['status']) {
                if (request::str('fn') == 'exec') {
                    if (empty($res['data']['download'])) {
                        Util::itoast('暂时没有任何文件需要更新！', $back_url, 'success');
                    } else {
                        $data = Util::get(UPGRADE_URL . '/?op=exec');
                        $res = json_decode($data, true);
                        if ($res && $res['status']) {
                            if (!Migrate::detect(true)) {
                                Util::itoast('更新成功！', $back_url, 'success');
                            }
                        }
                    }
                } else {
                    $tpl_data['upgrade']['settings'] = $res['data']['settings'];
                    $processFile = function ($arr) {
                        $result = [];
                        foreach ($arr as $filename) {
                            $fi = [
                                'filename' => $filename,
                                'dest' => $filename,
                            ];
                            $local_file = MODULE_ROOT . $filename;
                            $stats = stat($local_file);
                            if ($stats) {
                                $fi['size'] = is_dir($local_file) ? '<文件夹>' : $stats[7];
                                $fi['createtime'] = (new DateTime("@{$stats[9]}"))->format('Y-m-d H:i:s');
                            }
                            $result[] = $fi;
                        }
                        return $result;
                    };
                    $tpl_data['upgrade']['download'] = $processFile($res['data']['download']);
                    $tpl_data['upgrade']['copy'] = $processFile($res['data']['copy']);
                    $tpl_data['upgrade']['move'] = $processFile($res['data']['move']);
                    $tpl_data['upgrade']['remove'] = $processFile($res['data']['remove']);
                }
            } else {
                $tpl_data['upgrade']['error'] = empty($res['data']['message']) ? '暂无无法检查升级！' : strval($res['data']['message']);
            }
        } else {
            $tpl_data['upgrade']['error'] = '检查更新失败！';
        }
    }

} elseif ($op == 'showTestingQrcode') {

    $url = Util::murl('testing');
    $result = Util::createQrcodeFile('testing', $url);

    if (is_error($result)) {
        JSON::fail('创建二维码文件失败！');
    }

    $content = app()->fetchTemplate('web/common/qrcode', [
        'title' => '用微信扫一扫，打开测试页面',
        'url' => Util::toMedia($result),
    ]);

    JSON::success([
        'title' => '测试入口',
        'content' => $content,
    ]);
} elseif ($op == 'showDeviceNearbyQrcode') {

    $url = Util::murl('util');
    $result = Util::createQrcodeFile('deviceNearby', $url);

    if (is_error($result)) {
        JSON::fail('创建二维码文件失败！');
    }

    $content = app()->fetchTemplate('web/common/qrcode', [
        'title' => '用微信扫一扫，打开附近设备',
        'url' => Util::toMedia($result),
    ]);

    JSON::success([
        'title' => '附近设备',
        'content' => $content,
    ]);
} elseif ($op == 'showAgentRegQrcode') {

    $url = Util::murl('mobile');
    $result = Util::createQrcodeFile('agent', $url);

    if (is_error($result)) {
        JSON::fail('创建二维码文件失败！');
    }

    $content = app()->fetchTemplate('web/common/qrcode', [
        'title' => '用微信扫一扫，打开代理商注册页面',
        'url' => Util::toMedia($result),
    ]);

    JSON::success([
        'title' => '代理商注册页面',
        'content' => $content,
    ]);
}

if (!(array_key_exists($op, $tpl_data['navs']) || $op == 'ctrl')) {
    Util::itoast('找不到这个配置页面！', $this->createWebUrl('settings'), 'error');
}

$tpl_data['op'] = $op;
$tpl_data['settings'] = $settings;

app()->showTemplate("web/settings/{$op}", $tpl_data);
