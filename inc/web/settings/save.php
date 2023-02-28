<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\accountModelObj;

$url = _W('siteroot');

Util::createApiRedirectFile('api.php', '', [
    'memo' => '这个文件是小程序请求转发程序!',
], function () use ($url) {
    return "
header(\"Access-Control-Allow-Origin: $url\");
header(\"Access-Control-Allow-Methods: GET,POST\");
header(\"Access-Control-Allow-Headers: Content-Type, STA-API, STA-WXAPI, STA-WXWEB, LLT-API, LLT-WXAPI\");
header(\"Access-Control-Max-Age: 86400\");

if (isset(\$_SERVER['HTTP_STA_API']) || isset(\$_SERVER['HTTP_LLT_API'])) {
    \$_GET['do'] = 'wxapi';
    \$_GET['vendor'] = \$_SERVER['HTTP_STA_API'] ?? \$_SERVER['HTTP_LLT_API'];
} elseif (isset(\$_SERVER['HTTP_STA_WXAPI']) || isset(\$_SERVER['HTTP_LLT_WXAPI'])) {
    \$_GET['do'] = 'wxxapi';
}  elseif (isset(\$_SERVER['HTTP_STA_WXWEB'])) {
    \$_GET['do'] = 'wxweb';
} else {
    if (\zovye\App::isApiEnabled()) {
        \$_GET['do'] = 'query';
    } else {
        exit('invalid request!');
    }
}
";
});

$settings = settings();
$page = request::str('page');

if ($page == 'device') {
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

    $settings['order']['waitQueue']['enabled'] = request::bool('waitQueueEnabled') ? 1 : 0;

    $settings['goods']['agent']['edit'] = request::bool('allowAgentEditGoods') ? 1 : 0;

    $settings['goods']['image']['proxy'] = [
        'url' => request::trim('goodsImageProxyURL'),
        'secret' => request::trim('goodsImageProxySecret'),
    ];

    $settings['order']['rollback']['enabled'] = request::bool('autoRollbackOrder') ? 1 : 0;
    $settings['order']['goods']['maxNum'] = request::int('orderGoodsMaxNum');

    $settings['device']['v-device']['enabled'] = request::bool('vDevice') ? 1 : 0;
    $settings['device']['lac']['enabled'] = request::bool('lacConfirm') ? 1 : 0;

} elseif ($page == 'user') {

    $settings['user']['verify']['enabled'] = request::bool('userVerify') ? 1 : 0;
    $settings['user']['verify']['maxtimes'] = max(1, request::int('maxtimes'));

    $settings['user']['verify18']['enabled'] = request::bool('userVerify18') ? 1 : 0;
    $settings['user']['verify18']['Title'] = request::trim('userVerify18Title');

    $settings['user']['discountPrice'] = request::float('discountPrice', 0, 2) * 100;

    if (App::isMustFollowAccountEnabled()) {
        $settings['mfa'] = [
            'enable' => request::bool('mustFollow') ? 1 : 0,
        ];
    }

    if (App::isBalanceEnabled()) {
        Config::balance('user', [
            'new' => request::int('newUser'),
            'ref' => request::int('newUserRef'),
        ], true);
    }

} elseif ($page == 'ctrl') {
    $url = request::trim('controlAddr');
    if (empty($url)) {
        $url = "http://127.0.0.1:8080";
    }
    if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
        $url = 'http://'.$url;
    }

    $settings['ctrl']['url'] = $url;
    $settings['ctrl']['appKey'] = request::trim('appKey');
    $settings['ctrl']['appSecret'] = request::trim('appSecret');
    $settings['ctrl']['checkSign'] = request::bool('checkSign') ? 1 : 0;

    if (empty($settings['ctrl']['signature'])) {
        $settings['ctrl']['signature'] = Util::random(32);
    }

    if (App::isChargingDeviceEnabled()) {
        Config::charging('server', [
            'url' => request::trim('ChargingServerURL'),
            'access_token' => request::trim('ChargingServerAccessToken'),
        ], true);
    }

    $settings['device']['v-device']['enabled'] = request::bool('vDevice') ? 1 : 0;
    $settings['goods']['lottery']['enabled'] = request::bool('lotteryGoods') ? 1 : 0;
    $settings['idcard']['verify']['enabled'] = request::bool('idCardVerify') ? 1 : 0;
    if (!$settings['idcard']['verify']['enabled']) {
        $settings['user']['verify']['enabled'] = 0;
    }

    $settings['device']['bluetooth']['enabled'] = request::bool('bluetoothDevice') ? 1 : 0;
    $settings['goods']['voucher']['enabled'] = request::bool('goodsVoucher') ? 1 : 0;

    Config::api('enabled', request::bool('API', false), true);

    $settings['custom']['mustFollow']['enabled'] = request::bool('mustFollow') ? 1 : 0;
    $settings['custom']['useAccountQRCode']['enabled'] = request::bool('useAccountQRCode') ? 1 : 0;
    $settings['custom']['bonus']['zero']['enabled'] = request::bool('zeroBonus') ? 1 : 0;
    $settings['custom']['device']['brief-page']['enabled'] = request::bool('deviceBriefPage') ? 1 : 0;
    $settings['custom']['smsPromo']['enabled'] = request::bool('smsPromoEnabled') ? 1 : 0;
    $settings['custom']['team']['enabled'] = request::bool('teamEnabled') ? 1 : 0;
    $settings['custom']['cztv']['enabled'] = request::bool('cztvEnabled') ? 1 : 0;
    $settings['custom']['flashEgg']['enabled'] = request::bool('flashEggEnabled') ? 1 : 0;

    Config::app('ad.sponsor.enabled', request::bool('sponsorAd'), true);

    $settings['account']['wx']['platform']['enabled'] = request::bool('wxPlatform') ? 1 : 0;
    $settings['account']['douyin']['enabled'] = request::bool('douyin') ? 1 : 0;

    $third_party_platform = [
        'jfbFAN' => [
            __NAMESPACE__.'\Account::createJFBAccount',
            'jfb.fan.enabled',
        ],

        'moscalesFAN' => [
            __NAMESPACE__.'\Account::createMoscaleAccount',
            'moscale.fan.enabled',
        ],
        'yunfenbaFAN' => [
            __NAMESPACE__.'\Account::createYunFenBaAccount',
            'yunfenba.fan.enabled',
        ],
        'ZJBaoFAN' => [
            __NAMESPACE__.'\Account::createZJBaoAccount',
            'zjbao.fan.enabled',
        ],
        'AQiinfoFAN' => [
            __NAMESPACE__.'\Account::createAQiinfoAccount',
            'AQiinfo.fan.enabled',
        ],
        'MeiPaFAN' => [
            __NAMESPACE__.'\Account::createMeiPaAccount',
            'meipa.fan.enabled',
        ],
        'kingFAN' => [
            __NAMESPACE__.'\Account::createKingFansAccount',
            'king.fan.enabled',
        ],
        'sntoFAN' => [
            __NAMESPACE__.'\Account::createSNTOAccount',
            'snto.fan.enabled',
        ],
        'yfbFAN' => [
            __NAMESPACE__.'\Account::createYFBAccount',
            'yfb.fan.enabled',
        ],
        'wxWorkFAN' => [
            __NAMESPACE__.'\Account::createWxWorkAccount',
            'wxWork.fan.enabled',
        ],
        'youFenFAN' => [
            __NAMESPACE__.'\Account::createYouFenAccount',
            'YouFen.fan.enabled',
        ],
        'mengMoFenFAN' => [
            __NAMESPACE__.'\Account::createMengMoAccount',
            'MengMo.fan.enabled',
        ],
        'yiDaoFAN' => [
            __NAMESPACE__.'\Account::createYiDaoAccount',
            'YiDao.fan.enabled',
        ],
        'weiSureFAN' => [
            __NAMESPACE__.'\Account::createWeiSureAccount',
            'weiSure.fan.enabled',
        ],
        'cloudFIFAN' => [
            __NAMESPACE__.'\Account::createCloudFIAccount',
            'cloudFI.fan.enabled',
        ],
    ];

    $accounts_updated = false;

    foreach ($third_party_platform as $key => $v) {
        $enabled = request::bool($key) ? 1 : 0;

        /** @var accountModelObj $acc */
        $acc = call_user_func($v[0]);
        if ($acc) {
            $acc->setState($enabled ? Account::NORMAL : Account::BANNED);
            $acc->save();
            if (!$enabled) {
                //如果是禁用公众号，则清空设备分配数据
                $acc->setAssignData();
            }
        }

        if (getArray($settings, $v[1]) != $enabled) {
            $accounts_updated = true;
        }

        setArray($settings, $v[1], $enabled);
    }

    if ($accounts_updated) {
        setArray($settings, 'accounts.last_update', ''.microtime(true));
    }

    $settings['custom']['DonatePay']['enabled'] = request::bool('DonatePay') ? 1 : 0;
    $settings['agent']['wx']['app']['enabled'] = request::bool('agentWxApp') ? 1 : 0;
    $settings['inventory']['enabled'] = request::bool('Inventory') ? 1 : 0;

    $balance_enabled = request::bool('UserBalance');
    Config::balance('enabled', $balance_enabled ? 1 : 0, true);
    if ($balance_enabled) {
        if (empty(Config::balance('app.key'))) {
            Config::balance('app.key', Util::random(32), true);
        }
    }

    Config::device('door.enabled', request::bool('DeviceWithDoor') ? 1 : 0, true);
    Config::charging('enabled', request::bool('ChargingDeviceEnabled') ? 1 : 0, true);
    Config::fueling('enabled', request::bool('FuelingDeviceEnabled') ? 1 : 0, true);

    $settings['app']['first']['enabled'] = request::bool('ZovyeAppFirstEnable') ? 1 : 0;

    $settings['app']['domain']['enabled'] = request::bool('MultiDomainEnable') ? 1 : 0;
    $settings['app']['domain']['main'] = trim(request::trim('mainUrl'), '\\\/');
    $settings['app']['domain']['bak'] = request::array('bakUrl');
    foreach ($settings['app']['domain']['bak'] as $index => &$url) {
        $url = trim($url, " \t\n\r\0\x0B\\\/");
        if (empty($url)) {
            unset($settings['app']['domain']['bak'][$index]);
        }
    }

    if ($settings['app']['first']['enabled']) {
        $module_url = str_replace(
            './',
            '/',
            $GLOBALS['_W']['siteroot'].'web'.we7::url(
                'module/welcome/display',
                ['module_name' => APP_NAME, 'uniacid' => We7::uniacid()]
            )
        );
        $files = [
            [
                'filename' => IA_ROOT.'/index.php',
                'content' => "<?php\r\nrequire './framework/bootstrap.inc.php';\r\nheader('Location: ' . '{$module_url}');\r\nexit();",
            ],
            [
                'filename' => IA_ROOT.'/framework/bootstrap.inc.php',
                'append' => true,
                'content' => "\r\n\r\nif(\$action == 'login'){\r\n\t\$_GPC['referer'] = '{$module_url}';\r\n}",
            ],
        ];
        foreach ($files as $file) {
            $content = file_get_contents($file['filename']);
            if ($content && stripos($content, $module_url) === false) {
                file_put_contents($file['filename'], $file['content'], $file['append'] ? FILE_APPEND : 0);
            }
        }
    }
} elseif ($page == 'agent') {

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

    $settings['agent']['reg']['mode'] = request::bool(
        'agentRegMode'
    ) ? Agent::REG_MODE_AUTO : Agent::REG_MODE_NORMAL;

    $settings['agent']['reg']['referral'] = request::bool('agentReferral') ? 1 : 0;
    $settings['agent']['device']['unbind'] = request::bool('deviceUnbind') ? 1 : 0;
    $settings['agent']['device']['fee'] = [
        'year' => intval(round(request::float('deviceFeeYear', 0.0, 2) * 100)),
    ];

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
                $settings['agent']['reg']['bonus']['principal'] = request::trim(
                    'principal',
                    CommissionBalance::PRINCIPAL_ORDER
                );
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

} elseif ($page == 'commission') {
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
            $settings['commission']['withdraw']['fee']['percent'] = min(
                100,
                max(0, round(request::int('withdraw_fee_permille') / 10))
            );
        }
    }

    $settings['commission']['agreement']['freq'] = request::trim('commission_agreement');

    if ($settings['commission']['agreement']['freq']) {
        $settings['commission']['agreement']['content'] = request::str('commission_agreement_content');
        $settings['commission']['agreement']['version'] = sha1($settings['commission']['agreement']['content']);
    }

    if (App::isBalanceEnabled()) {
        Config::balance('order.commission.val', (int)(request::float('balanceOrderPrice', 0, 2) * 100), true);
    }

} elseif ($page == 'wxapp') {

    $settings['agentWxapp'] = [
        'title' => request::trim('WxAppTitle'),
        'name' => request::trim('WxAppName'),
        'key' => request::trim('WxAppKey'),
        'secret' => request::trim('WxAppSecret'),
        'username' => request::trim('WxAppUsername'),
    ];

    if (App::isBalanceEnabled()) {
        $reward = Config::app('wxapp.advs.reward', []);
        $reward['id'] = request::str('reward');
        Config::app('wxapp.advs', [
            'banner' => [
                'id' => request::str('banner'),
            ],
            'reward' => $reward,
            'interstitial' => [
                'id' => request::str('interstitial'),
            ],
            'video' => [
                'id' => request::str('video'),
            ],
        ], true);
    }

    Config::app('wxapp.message-push', [
        'token' => request::trim('WxAppPushMsgEncodingToken'),
        'encodingAESkey' => request::trim('WxAppPushMsgEncodingAESKey'),
        'msgEncodingType' => request::int('WxAppPushMsgEncodingType'),
        'msgTitle' => request::trim('WxAppPushMsgTitle'),
        'msgDesc' => request::trim('WxAppPushMsgDesc'),
        'msgThumb' => request::trim('WxAppPushMsgThumb'),
    ], true);

} elseif ($page == 'account') {

    $settings['misc']['account']['priority'] = request::trim('accountPriority');

    if (App::isWxPlatformEnabled()) {
        $settings['account']['wx']['platform']['config']['appid'] = request::trim('wxPlatformAppID');
        $settings['account']['wx']['platform']['config']['secret'] = request::trim('wxPlatformAppSecret');
        $settings['account']['wx']['platform']['config']['token'] = request::trim('wxPlatformToken');
        $settings['account']['wx']['platform']['config']['key'] = request::trim('wxPlatformKey');
    }

    if (App::isDouyinEnabled()) {
        Config::douyin('client', [
            'key' => request::trim('douyinClientKey'),
            'secret' => request::trim('douyinClientSecret'),
        ], true);
    }

    if (App::isCZTVEnabled()) {
        Config::cztv('client', [
            'appid' => request::trim('cztvAppId'),
            'redirect_url' => request::trim('cztvRedirectURL'),
            'account_uid' => request::trim('cztvAccountUID'),
        ], true);
    }

    $settings['account']['log']['enabled'] = request::bool('accountQueryLog') ? 1 : 0;

    $settings['misc']['pushAccountMsg_type'] = request::trim('pushAccountMsg_type');
    $settings['misc']['pushAccountMsg_val'] = request::trim('pushAccountMsg_val');
    $settings['misc']['pushAccountMsg_delay'] = request::int('pushAccountMsg_delay');
    $settings['misc']['maxAccounts'] = request::int('maxAccounts');
    $settings['user']['maxTotalFree'] = request::int('maxTotalFree');
    $settings['user']['maxFree'] = request::int('maxFree');
    $settings['misc']['accountsPromote'] = request::bool('accountsPromote') ? 1 : 0;

    $settings['order']['retry']['last'] = request::int('orderRetryLastTime');
    $settings['order']['retry']['max'] = request::int('orderRetryMaxCount');

} elseif ($page == 'notice') {
    $settings['notice'] = [
        'sms' => [
            'url' => 'https://v.juhe.cn/sms/send?',
            'appkey' => request::trim('smsAppkey'),
            'verify' => request::trim('smsVerify'),
        ],
        'reload_smstplid' => request::trim('reloadSMSTplid'),
        'order_tplid' => request::trim('order_tplid'),
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

} elseif ($page == 'misc') {
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

    $settings['api'] = [
        'account' => request::trim('account'),
    ];

    $settings['device']['upload'] = [
        'url' => request::trim('deviceUploadApiUrl'),
        'key' => request::trim('deviceUploadAppKey'),
        'secret' => request::trim('deviceUploadAppSecret'),
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
        $settings['custom']['bonus']['zero']['order'] = [
            'f' => request::bool('zeroBonusOrderFree') ? 1 : 0,
            'p' => request::bool('zeroBonusOrderPay') ? 1 : 0,
        ];
    }

    Config::notify('order', [
        'key' => request::str('orderNotifyAppKey'),
        'url' => request::trim('orderNotifyUrl'),
        'f' => request::bool('orderNotifyFree'),
        'p' => request::bool('orderNotifyPay'),
    ], true);

    Config::notify('inventory', [
        'key' => request::str('inventoryAccessKey'),
    ], true);

} elseif ($page == 'payment') {
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

        $settings['pay']['wx']['v3']['serial'] = request::trim('v3Serial');
        $settings['pay']['wx']['v3']['pem'] = [
            'cert' => request::trim('V3cert'),
            'key' => request::trim('V3key'),
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
        $settings['pay']['lcsw']['wx'] = request::bool('lcsw_weixin');
        $settings['pay']['lcsw']['ali'] = request::bool('lcsw_ali');
        $settings['pay']['lcsw']['wxapp'] = request::bool('lcsw_wxapp');
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

    if ($settings['pay']['SQB']['enable']) {
        $settings['pay']['SQB']['wx'] = request::bool('SQB_weixin');
        $settings['pay']['SQB']['ali'] = request::bool('SQB_ali');
        $settings['pay']['SQB']['wxapp'] = request::bool('SQB_wxapp');
        Util::createApiRedirectFile('/payment/SQB.php', 'payresult', [
            'headers' => [
                'HTTP_USER_AGENT' => 'SQB_notify',
            ],
            'op' => 'notify',
            'from' => 'SQB',
        ]);
    }

} elseif ($page == 'data_view') {
    $db_arr = [];
    $res = m('data_view')->findAll();
    foreach ($res as $item) {
        $db_arr[$item->getK()] = $item->getV();
    }

    $template_keys = [
        'title',
        'total_sale_init',
        'total_sale_freq',
        'total_sale_section1',
        'total_sale_section2',
        'today_sale_init',
        'today_sale_freq',
        'today_sale_section1',
        'today_sale_section2',
        'total_order_init',
        'total_order_freq',
        'total_order_section1',
        'total_order_section2',
        'today_order_init',
        'today_order_freq',
        'today_order_section1',
        'today_order_section2',
        'user_man',
        'user_woman',
        'income_wx',
        'income_ali',
        'g1',
        'g2',
        'g3',
        'g4',
        'g5',
        'g6',
        'g7',
        'g8',
        'g9',
        'g10',
        'p1',
        'p2',
        'p3',
        'p4',
        'p5',
        'p6',
        'p7',
        'p8',
        'p9',
        'p10',
        'p11',
        'p12',
        'p13',
        'p14',
        'p15',
        'p16',
        'p17',
        'p18',
        'p19',
        'p20',
        'p21',
        'p22',
        'p23',
        'p24',
        'p25',
        'p26',
        'p27',
        'p28',
        'p29',
        'p30',
        'p31',
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
} elseif ($page == 'balance') {

    if (App::isBalanceEnabled()) {
        Config::balance('sign.bonus', [
            'enabled' => request::bool('dailySignInEnabled') ? 1 : 0,
            'min' => request::int('dailySignInBonusMin'),
            'max' => max(request::int('dailySignInBonusMin'), request::int('dailySignInBonusMax')),
        ], true);

        Config::balance('app.notify_url', request::trim('balanceNotifyUrl'), true);
        Config::balance('order.as', request::str('balanceOrderAs'), true);
        Config::balance('order.auto_rb', request::bool('autoRollbackOrderBalance') ? 1 : 0, true);

        $promote_opts = request::array('accountPromoteBonusOption', []);
        foreach (['third_platform', 'account', 'video', 'wxapp', 'douyin'] as $name) {
            Config::balance("account.promote_bonus.$name", in_array($name, $promote_opts) ? 1 : 0, true);
        }

        Config::balance('account.promote_bonus.min', request::int('accountPromoteBonusMin'), true);
        Config::balance(
            'account.promote_bonus.max',
            max(request::int('accountPromoteBonusMin'), request::int('accountPromoteBonusMax')),
            true
        );
    }
}

if (app()->saveSettings($settings)) {
    Util::itoast('设置保存成功！', $this->createWebUrl('settings', ['page' => $page]), 'success');
}

Util::itoast('设置保存失败！', $this->createWebUrl('settings', ['page' => $page]), 'error');