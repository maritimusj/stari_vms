<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

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
$page = Request::str('page');

if ($page == 'device') {
    $settings['device']['autoJoin'] = Request::bool('newDeviceAutoJoin') ? 1 : 0;
    $settings['device']['errorDown'] = Request::bool('errorDown') ? 1 : 0;
    $settings['device']['clearErrorCode'] = Request::bool('clearErrorCode') ? 1 : 0;
    $settings['device']['remainWarning'] = Request::int('remainWarning');
    $settings['device']['waitTimeout'] = max(10, Request::int('waitTimeout'));
    $settings['device']['lockRetries'] = Request::int('lockRetries');
    $settings['device']['lockRetryDelay'] = Request::int('lockRetryDelay');
    $settings['device']['lockTimeout'] = Request::int('lockTimeout');

    $settings['device']['get'] = [
        'theme' => Request::str('theme', 'default'),
    ];

    $settings['device']['lost'] = Request::int('lost');
    $settings['device']['issuing'] = Request::int('issuing');

    $settings['misc']['siteTitle'] = Request::trim('siteTitle');
    $settings['misc']['siteCopyrights'] = Request::trim('siteCopyrights');
    $settings['misc']['banner'] = Request::trim('banner');
    $settings['misc']['siteWarning'] = Request::trim('siteWarning');

    $settings['user']['location']['validate']['enabled'] = Request::bool('userLocationEnabled') ? 1 : 0;

    if ($settings['user']['location']['validate']['enabled']) {
        $settings['user']['location']['validate']['distance'] = min(
            5000,
            max(1, Request::int('userLocationDistance'))
        );
        $settings['user']['location']['validate']['expired'] = min(
            720,
            max(1, Request::int('userLocationExpired'))
        );
    }

    $settings['user']['location']['appkey'] = Request::trim('lbsKey', DEFAULT_LBS_KEY);

    $settings['device']['shipment']['balanced'] = Request::bool('shipmentBalance') ? 1 : 0;

    $settings['order']['waitQueue']['enabled'] = Request::bool('waitQueueEnabled') ? 1 : 0;

    $settings['goods']['agent']['edit'] = Request::bool('allowAgentEditGoods') ? 1 : 0;

    $settings['goods']['image']['proxy'] = [
        'url' => Request::trim('goodsImageProxyURL'),
        'secret' => Request::trim('goodsImageProxySecret'),
    ];

    $settings['order']['rollback']['enabled'] = Request::bool('autoRollbackOrder') ? 1 : 0;
    $settings['order']['goods']['maxNum'] = Request::int('orderGoodsMaxNum');

    $settings['device']['v-device']['enabled'] = Request::bool('vDevice') ? 1 : 0;
    $settings['device']['lac']['enabled'] = Request::bool('lacConfirm') ? 1 : 0;
    $settings['device']['eventLog']['enabled'] = Request::bool('deviceEventLogEnabled') ? 1 : 0;

} elseif ($page == 'user') {

    $settings['user']['verify']['enabled'] = Request::bool('userVerify') ? 1 : 0;
    $settings['user']['verify']['maxtimes'] = max(1, Request::int('maxtimes'));

    $settings['user']['verify18']['enabled'] = Request::bool('userVerify18') ? 1 : 0;
    $settings['user']['verify18']['Title'] = Request::trim('userVerify18Title');

    $settings['user']['discountPrice'] = Request::float('discountPrice', 0, 2) * 100;

    if (App::isMustFollowAccountEnabled()) {
        $settings['mfa'] = [
            'enable' => Request::bool('mustFollow') ? 1 : 0,
        ];
    }

} elseif ($page == 'ctrl') {

    $url = Request::trim('controlAddr');
    if (empty($url)) {
        $url = "http://127.0.0.1:8080";
    }
    if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
        $url = 'http://'.$url;
    }

    $settings['ctrl']['url'] = $url;
    $settings['ctrl']['appKey'] = Request::trim('appKey');
    $settings['ctrl']['appSecret'] = Request::trim('appSecret');
    $settings['ctrl']['checkSign'] = Request::bool('checkSign') ? 1 : 0;

    if (empty($settings['ctrl']['signature'])) {
        $settings['ctrl']['signature'] = Util::random(32);
    }

    if (App::isChargingDeviceEnabled()) {
        Config::charging('server', [
            'url' => Request::trim('ChargingServerURL'),
            'access_token' => Request::trim('ChargingServerAccessToken'),
        ], true);
    }

    $settings['device']['v-device']['enabled'] = Request::bool('vDevice') ? 1 : 0;
    $settings['goods']['lottery']['enabled'] = Request::bool('lotteryGoods') ? 1 : 0;
    $settings['idcard']['verify']['enabled'] = Request::bool('idCardVerify') ? 1 : 0;
    if (!$settings['idcard']['verify']['enabled']) {
        $settings['user']['verify']['enabled'] = 0;
    }

    $settings['device']['bluetooth']['enabled'] = Request::bool('bluetoothDevice') ? 1 : 0;
    $settings['goods']['voucher']['enabled'] = Request::bool('goodsVoucher') ? 1 : 0;

    Config::api('enabled', Request::bool('API'), true);

    $settings['custom']['goodsPackage']['enabled'] = Request::bool('goodsPackage') ? 1 : 0;
    $settings['custom']['mustFollow']['enabled'] = Request::bool('mustFollow') ? 1 : 0;
    $settings['custom']['useAccountQRCode']['enabled'] = Request::bool('useAccountQRCode') ? 1 : 0;
    $settings['custom']['bonus']['zero']['enabled'] = Request::bool('zeroBonus') ? 1 : 0;
    $settings['custom']['device']['brief-page']['enabled'] = Request::bool('deviceBriefPage') ? 1 : 0;
    $settings['custom']['smsPromo']['enabled'] = Request::bool('smsPromoEnabled') ? 1 : 0;
    $settings['custom']['team']['enabled'] = Request::bool('teamEnabled') ? 1 : 0;
    $settings['custom']['cztv']['enabled'] = Request::bool('cztvEnabled') ? 1 : 0;
    $settings['custom']['flashEgg']['enabled'] = Request::bool('flashEggEnabled') ? 1 : 0;
    $settings['custom']['promoter']['enabled'] = Request::bool('promoterEnabled') ? 1 : 0;
    $settings['custom']['GDCVMachine']['enabled'] = Request::bool('GDCVMachineEnabled') ? 1 : 0;
    $settings['custom']['MultiGoodsItem']['enabled'] = Request::bool('MultiGoodsItemEnabled') ? 1 : 0;
    $settings['custom']['getUserBalanceByMobile']['enabled'] = Request::bool('getUserBalanceByMobileEnabled') ? 1 : 0;
    $settings['custom']['TKPromoting']['enabled'] = Request::bool('TKPromotingEnabled') ? 1 : 0;

    Config::app('ad.sponsor.enabled', Request::bool('sponsorAd'), true);

    $settings['account']['wx']['platform']['enabled'] = Request::bool('wxPlatform') ? 1 : 0;
    $settings['account']['douyin']['enabled'] = Request::bool('douyin') ? 1 : 0;

    $third_party_platform = [
        'jfbFAN' => [[Account::class, 'createJFBAccount'], 'jfb.fan.enabled'],
        'moscalesFAN' => [[Account::class, 'createMoscaleAccount'], 'moscale.fan.enabled'],
        'yunfenbaFAN' => [[Account::class, 'createYunFenBaAccount'], 'yunfenba.fan.enabled'],
        'ZJBaoFAN' => [[Account::class, 'createZJBaoAccount'], 'zjbao.fan.enabled'],
        'AQiinfoFAN' => [[Account::class, 'createAQiinfoAccount'], 'AQiinfo.fan.enabled'],
        'MeiPaFAN' => [[Account::class, 'createMeiPaAccount'], 'meipa.fan.enabled'],
        'kingFAN' => [[Account::class, 'createKingFansAccount'], 'king.fan.enabled'],
        'sntoFAN' => [[Account::class, 'createSNTOAccount'], 'snto.fan.enabled'],
        'yfbFAN' => [[Account::class, 'createYFBAccount'], 'yfb.fan.enabled'],
        'wxWorkFAN' => [[Account::class, 'createWxWorkAccount'], 'wxWork.fan.enabled'],
        'youFenFAN' => [[Account::class, 'createYouFenAccount'], 'YouFen.fan.enabled'],
        'mengMoFenFAN' => [[Account::class, 'createMengMoAccount'], 'MengMo.fan.enabled'],
        'yiDaoFAN' => [[Account::class, 'createYiDaoAccount'], 'YiDao.fan.enabled'],
        'weiSureFAN' => [[Account::class, 'createWeiSureAccount'], 'weiSure.fan.enabled'],
        'cloudFIFAN' => [[Account::class, 'createCloudFIAccount'], 'cloudFI.fan.enabled'],
    ];

    $accounts_updated = false;

    foreach ($third_party_platform as $key => $v) {
        $enabled = Request::bool($key) ? 1 : 0;

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

    $settings['custom']['DonatePay']['enabled'] = Request::bool('DonatePay') ? 1 : 0;
    $settings['agent']['wx']['app']['enabled'] = Request::bool('agentWxApp') ? 1 : 0;
    $settings['inventory']['enabled'] = Request::bool('Inventory') ? 1 : 0;

    $balance_enabled = Request::bool('UserBalance');
    Config::balance('enabled', $balance_enabled ? 1 : 0, true);
    if ($balance_enabled) {
        if (empty(Config::balance('app.key'))) {
            Config::balance('app.key', Util::random(32), true);
        }
    }

    Config::device('door.enabled', Request::bool('DeviceWithDoor') ? 1 : 0, true);
    Config::device('schedule.enabled', Request::bool('DeviceScheduleEnabled') ? 1 : 0, true);
    Config::charging('enabled', Request::bool('ChargingDeviceEnabled') ? 1 : 0, true);
    Config::fueling('enabled', Request::bool('FuelingDeviceEnabled') ? 1 : 0, true);

    $settings['app']['first']['enabled'] = Request::bool('ZovyeAppFirstEnable') ? 1 : 0;

    $settings['app']['domain']['enabled'] = Request::bool('MultiDomainEnable') ? 1 : 0;
    $settings['app']['domain']['main'] = trim(Request::trim('mainUrl'), '\\\/');
    $settings['app']['domain']['bak'] = Request::array('bakUrl');
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
                'content' => "\r\n\r\nif(\$action == 'login'){\r\n\t\$_GPC['referer'] = '$module_url';\r\n}",
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

    $settings['agent']['order']['refund'] = Request::bool('allowAgentRefund') ? 1 : 0;

    if ($settings['inventory']['enabled']) {
        $settings['inventory']['goods']['mode'] = Request::bool('inventoryGoodsLack') ? 1 : 0;
    }

    $settings['agent']['msg_tplid'] = Request::trim('agentMsg');
    $settings['agent']['levels'] = [
        'level0' => ['clr' => Request::trim('clr0'), 'title' => Request::trim('level0')],
        'level1' => ['clr' => Request::trim('clr1'), 'title' => Request::trim('level1')],
        'level2' => ['clr' => Request::trim('clr2'), 'title' => Request::trim('level2')],
        'level3' => ['clr' => Request::trim('clr3'), 'title' => Request::trim('level3')],
        'level4' => ['clr' => Request::trim('clr4'), 'title' => Request::trim('level4')],
        'level5' => ['clr' => Request::trim('clr5'), 'title' => Request::trim('level5')],
    ];

    $settings['agent']['reg']['mode'] = Request::bool(
        'agentRegMode'
    ) ? Agent::REG_MODE_AUTO : Agent::REG_MODE_NORMAL;

    $settings['agent']['reg']['referral'] = Request::bool('agentReferral') ? 1 : 0;
    $settings['agent']['device']['unbind'] = Request::bool('deviceUnbind') ? 1 : 0;
    $settings['agent']['device']['fee'] = [
        'year' => intval(round(Request::float('deviceFeeYear', 0.0, 2) * 100)),
    ];

    if ($settings['agent']['reg']['mode'] == Agent::REG_MODE_AUTO) {

        $settings['agent']['reg']['superior'] = Request::bool('YzshopSuperiorRelationship') ? 'yz' : 'n/a';
        $settings['agent']['reg']['level'] = Request::str('agentRegLevel');
        $settings['agent']['reg']['commission_fee'] = round(Request::float('agentCommissionFee', 0, 2) * 100);
        $settings['agent']['reg']['commission_fee_type'] = Request::bool('feeType') ? 1 : 0;

        $settings['agent']['reg']['funcs'] = Util::parseAgentFNsFromGPC();

        if ($settings['commission']['enabled']) {
            //佣金分享
            $settings['agent']['reg']['rel_gsp']['enabled'] = Request::bool('agentRelGsp') ? 1 : 0;
            $settings['agent']['reg']['gsp_mode_type'] = Request::str('gsp_mode_type', GSP::PERCENT);

            if ($settings['agent']['reg']['rel_gsp']['enabled']) {

                $rel_0 = max(0, Request::float('rel_gsp_level0', 0, 2) * 100);
                $rel_1 = max(0, Request::float('rel_gsp_level1', 0, 2) * 100);
                $rel_2 = max(0, Request::float('rel_gsp_level2', 0, 2) * 100);
                $rel_3 = max(0, Request::float('rel_gsp_level3', 0, 2) * 100);

                if (in_array(
                    $settings['agent']['reg']['gsp_mode_type'],
                    [GSP::AMOUNT, GSP::AMOUNT_PER_GOODS]
                )) {
                    $rel_0 = intval($rel_0);
                    $rel_1 = intval($rel_1);
                    $rel_2 = intval($rel_2);
                    $rel_3 = intval($rel_3);
                } else {
                    $rel_3 = min(10000, $rel_3);
                    $rel_2 = min(10000, $rel_2);
                    $rel_1 = min(10000, $rel_1);
                    $rel_0 = 10000 - $rel_1 - $rel_2 - $rel_3;
                }

                $settings['agent']['reg']['rel_gsp']['level0'] = $rel_0;
                $settings['agent']['reg']['rel_gsp']['level1'] = $rel_1;
                $settings['agent']['reg']['rel_gsp']['level2'] = $rel_2;
                $settings['agent']['reg']['rel_gsp']['level3'] = $rel_3;

                $settings['agent']['reg']['rel_gsp']['order'] = [
                    'f' => Request::bool('freeOrderGSP') ? 1 : 0,
                    'b' => Request::bool('balanceOrderGSP') ? 1 : 0,
                    'p' => Request::bool('payOrderGSP') ? 1 : 0,
                ];
            }
            //佣金奖励
            $settings['agent']['reg']['bonus']['enabled'] = Request::bool('agentBonusEnabled') ? 1 : 0;
            if ($settings['agent']['reg']['bonus']['enabled']) {
                $settings['agent']['reg']['bonus']['principal'] = Request::trim(
                    'principal',
                    CommissionBalance::PRINCIPAL_ORDER
                );
                $settings['agent']['reg']['bonus']['order'] = [
                    'f' => Request::bool('freeOrder') ? 1 : 0,
                    'b' => Request::bool('balanceOrder') ? 1 : 0,
                    'p' => Request::bool('payOrder') ? 1 : 0,
                ];

                $settings['agent']['reg']['bonus']['level0'] = Request::float('rel_bonus_level0', 0, 2) * 100;
                $settings['agent']['reg']['bonus']['level1'] = Request::float('rel_bonus_level1', 0, 2) * 100;
                $settings['agent']['reg']['bonus']['level2'] = Request::float('rel_bonus_level2', 0, 2) * 100;
                $settings['agent']['reg']['bonus']['level3'] = Request::float('rel_bonus_level3', 0, 2) * 100;
            }
        }
    }

    $settings['agent']['yzshop']['goods_limits']['enabled'] = Request::bool('YzshopGoodsLimits') ? 1 : 0;

    if ($settings['agent']['yzshop']['goods_limits']['enabled']) {
        $goods_id = Request::int('goodsID');
        if ($goods_id) {
            $settings['agent']['yzshop']['goods_limits']['id'] = $goods_id;
        }

        $settings['agent']['yzshop']['goods_limits']['OR'] = max(1, Request::int('goodsOR'));
        $settings['agent']['yzshop']['goods_limits']['title'] = Request::trim('restrictGoodsTitle');

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
            'enabled' => Request::bool('agent_agreement'),
            'content' => Request::trim('agent_agreement_content'),
        ],
        'keeper' => [
            'enabled' => Request::bool('keeper_agreement'),
            'content' => Request::trim('keeper_agreement_content'),
        ],
    ], true);

} elseif ($page == 'commission') {
    $settings['commission']['enabled'] = Request::bool('commission') ? 1 : 0;

    if ($settings['commission']['enabled']) {
        $settings['commission']['withdraw'] = [
            'times' => Request::int('withdraw_times'),
            'min' => Request::float('withdraw_min', 0, 2) * 100,
            'max' => Request::float('withdraw_max', 0, 2) * 100,
            'count' => [
                'month' => Request::int('withdraw_maxcount'),
            ],
            'pay_type' => Request::int('withdraw_pay_type'),
            'fee' => [
                'permille' => min(1000, max(0, Request::int('withdraw_fee_permille'))),
                'min' => max(0, round(Request::int('withdraw_fee_min') * 100)),
                'max' => max(0, round(Request::int('withdraw_fee_max') * 100)),
            ],
            'bank_card' => Request::int('withdraw_bank_card'),
        ];

        if (Request::isset('withdraw_fee_percent')) {
            $settings['commission']['withdraw']['fee']['percent'] = min(100, max(0, Request::int('percent')));
        } else {
            $settings['commission']['withdraw']['fee']['percent'] = min(
                100,
                max(0, round(Request::int('withdraw_fee_permille') / 10))
            );
        }
    }

    $settings['commission']['agreement']['freq'] = Request::trim('commission_agreement');

    if ($settings['commission']['agreement']['freq']) {
        $settings['commission']['agreement']['content'] = Request::str('commission_agreement_content');
        $settings['commission']['agreement']['version'] = sha1($settings['commission']['agreement']['content']);
    }

    if (App::isBalanceEnabled()) {
        Config::balance('order.commission.val', (int)(Request::float('balanceOrderPrice', 0, 2) * 100), true);
    }

} elseif ($page == 'wxapp') {

    $settings['agentWxapp'] = [
        'title' => Request::trim('WxAppTitle'),
        'name' => Request::trim('WxAppName'),
        'key' => Request::trim('WxAppKey'),
        'secret' => Request::trim('WxAppSecret'),
        'username' => Request::trim('WxAppUsername'),
    ];

    if (App::isBalanceEnabled()) {
        $reward = Config::app('wxapp.advs.reward', []);
        $reward['id'] = Request::str('reward');
        Config::app('wxapp.advs', [
            'banner' => [
                'id' => Request::str('banner'),
            ],
            'reward' => $reward,
            'interstitial' => [
                'id' => Request::str('interstitial'),
            ],
            'video' => [
                'id' => Request::str('video'),
            ],
        ], true);
    }

    Config::app('wxapp.message-push', [
        'token' => Request::trim('WxAppPushMsgEncodingToken'),
        'encodingAESkey' => Request::trim('WxAppPushMsgEncodingAESKey'),
        'msgEncodingType' => Request::int('WxAppPushMsgEncodingType'),
        'msgTitle' => Request::trim('WxAppPushMsgTitle'),
        'msgDesc' => Request::trim('WxAppPushMsgDesc'),
        'msgThumb' => Request::trim('WxAppPushMsgThumb'),
    ], true);

} elseif ($page == 'account') {

    $settings['misc']['account']['priority'] = Request::trim('accountPriority');

    if (App::isWxPlatformEnabled()) {
        $settings['account']['wx']['platform']['config']['appid'] = Request::trim('wxPlatformAppID');
        $settings['account']['wx']['platform']['config']['secret'] = Request::trim('wxPlatformAppSecret');
        $settings['account']['wx']['platform']['config']['token'] = Request::trim('wxPlatformToken');
        $settings['account']['wx']['platform']['config']['key'] = Request::trim('wxPlatformKey');
    }

    if (App::isDouyinEnabled()) {
        Config::douyin('client', [
            'key' => Request::trim('douyinClientKey'),
            'secret' => Request::trim('douyinClientSecret'),
        ], true);
    }

    if (App::isCZTVEnabled()) {
        Config::cztv('client', [
            'appid' => Request::trim('cztvAppId'),
            'redirect_url' => Request::trim('cztvRedirectURL'),
            'account_uid' => Request::trim('cztvAccountUID'),
        ], true);
    }

    $settings['account']['log']['enabled'] = Request::bool('accountQueryLog') ? 1 : 0;

    $settings['misc']['pushAccountMsg_type'] = Request::trim('pushAccountMsg_type');
    $settings['misc']['pushAccountMsg_val'] = Request::trim('pushAccountMsg_val');
    $settings['misc']['pushAccountMsg_delay'] = Request::int('pushAccountMsg_delay');
    $settings['misc']['maxAccounts'] = Request::int('maxAccounts');
    $settings['user']['maxTotalFree'] = Request::int('maxTotalFree');
    $settings['user']['maxFree'] = Request::int('maxFree');
    $settings['misc']['accountsPromote'] = Request::bool('accountsPromote') ? 1 : 0;

    $settings['order']['retry']['last'] = Request::int('orderRetryLastTime');
    $settings['order']['retry']['max'] = Request::int('orderRetryMaxCount');

} elseif ($page == 'notice') {
    $settings['notice'] = [
        'sms' => [
            'url' => 'https://v.juhe.cn/sms/send?',
            'appkey' => Request::trim('smsAppkey'),
            'verify' => Request::trim('smsVerify'),
        ],
        'reload_smstplid' => Request::trim('reloadSMSTplid'),
        'order_tplid' => Request::trim('order_tplid'),
        'reload_tplid' => Request::trim('reload_tplid'),
        'agentReq_tplid' => Request::trim('agentReqTplid'),
        'deviceerr_tplid' => Request::trim('deviceErrorTplid'),
        'deviceOnline_tplid' => Request::trim('deviceOnlineTplid'),
        'agentresult_tplid' => Request::trim('agentResultTplid'),
        'withdraw_tplid' => Request::trim('withdrawTplid'),
        'advReviewTplid' => Request::trim('advReviewTplid'),
        'advReviewResultTplid' => Request::trim('advReviewResultTplid'),
        'delay' => [
            'remainWarning' => Request::int('remainWarningDelay') ?: 1,
            'deviceerr' => Request::int('deviceErrorDelay') ?: 1,
            'deviceOnline' => Request::int('deviceOnlineDelay') ?: 1,
        ],
    ];

    $reviewAdminUserId = Request::int('reviewAdminUser');

    if ($reviewAdminUserId) {
        $user = User::get($reviewAdminUserId);
        if ($user) {
            $settings['notice']['reviewAdminUserId'] = $reviewAdminUserId;
        }
    }

    $authorizedAdminUserId = Request::int('authorizedAdminUser');

    if ($authorizedAdminUserId) {
        $user = User::get($authorizedAdminUserId);
        if ($user) {
            $settings['notice']['authorizedAdminUserId'] = $authorizedAdminUserId;
        }
    }

    $withdrawAdminUserId = Request::int('withdrawAdminUser');

    if ($withdrawAdminUserId) {
        $user = User::get($withdrawAdminUserId);
        if ($user) {
            $settings['notice']['withdrawAdminUserId'] = $withdrawAdminUserId;
        }
    }

} elseif ($page == 'misc') {
    $settings['misc']['redirect'] = [
        'success' => [
            'url' => Request::trim('success_url'),
        ],
        'fail' => [
            'url' => Request::trim('fail_url'),
        ],
    ];

    $settings['we7credit']['enabled'] = Request::bool('we7credit') ? 1 : 0;

    if ($settings['we7credit']['enabled']) {

        $settings['we7credit']['type'] = Request::trim('credit_type');
        $settings['we7credit']['val'] = Request::int('credit_val');
        $settings['we7credit']['require'] = Request::int('credit_require');
    }

    $settings['advs']['assign'] = [
        'multi' => Request::bool('advsAssignMultilMode') ? 1 : 0,
    ];

    $settings['misc']['qrcode']['default_url'] = Request::trim('default_url');

    $settings['api'] = [
        'account' => Request::trim('account'),
    ];

    $settings['device']['upload'] = [
        'url' => Request::trim('deviceUploadApiUrl'),
        'key' => Request::trim('deviceUploadAppKey'),
        'secret' => Request::trim('deviceUploadAppSecret'),
    ];

    if (App::isDonatePayEnabled()) {
        Config::donatePay('qsc', [
            'title' => Request::trim('donatePayTitle'),
            'desc' => Request::trim('donatePayDesc'),
            'url' => Request::trim('donatePayUrl'),
        ], true);
    }

    if (App::isZeroBonusEnabled()) {
        $settings['custom']['bonus']['zero']['v'] = min(100, Request::float('zeroBonus', 0, 2));
        $settings['custom']['bonus']['zero']['order'] = [
            'f' => Request::bool('zeroBonusOrderFree') ? 1 : 0,
            'p' => Request::bool('zeroBonusOrderPay') ? 1 : 0,
        ];
    }

    Config::notify('order', [
        'key' => Request::str('orderNotifyAppKey'),
        'url' => Request::trim('orderNotifyUrl'),
        'f' => Request::bool('orderNotifyFree'),
        'p' => Request::bool('orderNotifyPay'),
    ], true);

    Config::notify('inventory', [
        'key' => Request::str('inventoryAccessKey'),
    ], true);

    if (App::isGDCVMachineEnabled()) {
        Config::GDCVMachine('config', [
            'url' => Request::trim('GDCVMachineApiUrl'),
            'agent' => Request::trim('GDCVMachineAgentCode'),
            'appId' => Request::trim('GDCVMachineAppId'),
            'token' => Request::trim('GDCVMachineToken'),
            'account' => Request::trim('GDCVMachineAccountUID'),
        ], true);
    }

    if (App::isTKPromotingEnabled()) {
        $config = [
            'id' => Request::trim('TKAppId'),
            'secret' => Request::trim('TKAppSecret'),
            'account_uid' => Request::trim('TKAccountUID'),
        ];

        Config::tk('config', $config, true);

        if ($config['id'] && $config['secret']) {
            // 注册回调通知网址
            $res = (new TKPromoting($config['id'], $config['secret']))->setNotifyUrl();
            Log::debug('tk', [
                'set notify url' => $res,
            ]);
        }
    }

} elseif ($page == 'payment') {
    $wx_enabled = Request::bool('wx') ? 1 : 0;
    $settings['pay']['wx']['enable'] = $wx_enabled;

    if ($wx_enabled) {
        $settings['pay']['wx']['appid'] = Request::trim('wxAppID');
        $settings['pay']['wx']['wxappid'] = Request::trim('wxxAppID');
        $settings['pay']['wx']['key'] = Request::trim('wxAppKey');
        $settings['pay']['wx']['mch_id'] = Request::trim('wxMCHID');
        $settings['pay']['wx']['pem'] = [
            'cert' => Request::trim('certPEM'),
            'key' => Request::trim('keyPEM'),
        ];

        $settings['pay']['wx']['v3']['serial'] = Request::trim('v3Serial');
        $settings['pay']['wx']['v3']['pem'] = [
            'cert' => Request::trim('V3cert'),
            'key' => Request::trim('V3key'),
        ];

        if (false === Util::createApiRedirectFile('payment/wx.php', 'payresult', [
                'headers' => [
                    'HTTP_USER_AGENT' => 'wx_notify',
                ],
                'op' => 'notify',
                'from' => 'wx',
            ])) {
            Response::toast('创建微信支付入口文件失败！');
        }
    }

    $lcsw_enabled = Request::bool('lcsw') ? 1 : 0;
    $settings['pay']['lcsw']['enable'] = $lcsw_enabled;

    if ($lcsw_enabled) {
        $settings['pay']['lcsw']['wx'] = Request::bool('lcsw_weixin');
        $settings['pay']['lcsw']['ali'] = Request::bool('lcsw_ali');
        $settings['pay']['lcsw']['wxapp'] = Request::bool('lcsw_wxapp');
        $settings['pay']['lcsw']['merchant_no'] = Request::trim('merchant_no');
        $settings['pay']['lcsw']['terminal_id'] = Request::trim('terminal_id');
        $settings['pay']['lcsw']['access_token'] = Request::trim('access_token');

        if (false === Util::createApiRedirectFile('payment/lcsw.php', 'payresult', [
                'headers' => [
                    'HTTP_USER_AGENT' => 'lcsw_notify',
                ],
                'op' => 'notify',
                'from' => 'lcsw',
            ])) {
            Response::toast('创建扫呗支付入口文件失败！');
        }
    }

    $settings['ali']['appid'] = Request::trim('ali_appid');
    $settings['ali']['pubkey'] = Request::trim('ali_pubkey');
    $settings['ali']['prikey'] = Request::trim('ali_prikey');

    $settings['alixapp']['id'] = Request::trim('alixapp_id');
    $settings['alixapp']['pubkey'] = Request::trim('alixapp_pubkey');
    $settings['alixapp']['prikey'] = Request::trim('alixapp_prikey');

    if ($settings['pay']['SQB']['enable']) {
        $settings['pay']['SQB']['wx'] = Request::bool('SQB_weixin');
        $settings['pay']['SQB']['ali'] = Request::bool('SQB_ali');
        $settings['pay']['SQB']['wxapp'] = Request::bool('SQB_wxapp');
        Util::createApiRedirectFile('/payment/SQB.php', 'payresult', [
            'headers' => [
                'HTTP_USER_AGENT' => 'SQB_notify',
            ],
            'op' => 'notify',
            'from' => 'SQB',
        ]);
    }

} elseif ($page == 'data_vw') {
    $db_arr = [];
    $res = m('data_vw')->findAll();
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
        if (Request::isset($val)) {
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

    $query = m('data_vw');
    foreach ($need_inserted_arr as $key => $val) {
        $query->create(['k' => $key, 'v' => $val, 'createtime' => time()]);
    }
} elseif ($page == 'balance') {

    if (App::isBalanceEnabled()) {
        Config::balance('sign.bonus', [
            'enabled' => Request::bool('dailySignInEnabled') ? 1 : 0,
            'min' => Request::int('dailySignInBonusMin'),
            'max' => max(Request::int('dailySignInBonusMin'), Request::int('dailySignInBonusMax')),
        ], true);

        Config::balance('app.notify_url', Request::trim('balanceNotifyUrl'), true);
        Config::balance('order.as', Request::str('balanceOrderAs'), true);
        Config::balance('order.auto_rb', Request::bool('autoRollbackOrderBalance') ? 1 : 0, true);

        $promote_opts = Request::array('accountPromoteBonusOption');
        foreach (['third_platform', 'account', 'video', 'wxapp', 'douyin'] as $name) {
            Config::balance("account.promote_bonus.$name", in_array($name, $promote_opts) ? 1 : 0, true);
        }

        Config::balance('account.promote_bonus.min', Request::int('accountPromoteBonusMin'), true);
        Config::balance(
            'account.promote_bonus.max',
            max(Request::int('accountPromoteBonusMin'), Request::int('accountPromoteBonusMax')),
            true
        );

        Config::balance('user', [
            'new' => Request::int('newUser'),
            'ref' => Request::int('newUserRef'),
        ], true);
    }
}

if (app()->saveSettings($settings)) {
    Response::toast('设置保存成功！', $this->createWebUrl('settings', ['page' => $page]), 'success');
}

Response::toast('设置保存失败！', $this->createWebUrl('settings', ['page' => $page]), 'error');