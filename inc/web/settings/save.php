<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\business\TKPromoting;
use zovye\domain\Account;
use zovye\domain\Agent;
use zovye\domain\CommissionBalance;
use zovye\domain\GSP;
use zovye\domain\PaymentConfig;
use zovye\domain\User;
use zovye\model\accountModelObj;
use zovye\model\data_vwModelObj;
use zovye\util\Helper;
use zovye\util\SQBUtil;
use zovye\util\Util;
use zovye\util\PayUtil;

$url = _W('siteroot');

Helper::createApiRedirectFile('api.php', '', [
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
    \$_GET['do'] = 'query';
}
";
});

$settings = settings();
$page = Request::str('page');

if ($page == 'device') {
    $settings['device']['autoJoin'] = Request::bool('newDeviceAutoJoin') ? 1 : 0;
    $settings['device']['errorDown'] = Request::bool('errorDown') ? 1 : 0;
    $settings['device']['clearErrorCode'] = Request::bool('clearErrorCode') ? 1 : 0;
    $settings['device']['errorInventoryOp'] = Request::bool('errorInventoryOp') ? 1 : 0;
    $settings['device']['remainWarning'] = Request::int('remainWarning');
    $settings['device']['waitTimeout'] = max(10, Request::int('waitTimeout'));
    $settings['device']['lockRetries'] = Request::int('lockRetries');
    $settings['device']['lockRetryDelay'] = Request::int('lockRetryDelay');

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
    $settings['order']['rollback']['delay'] = max(0, Request::int('rollbackOrderDelay'));
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

    $settings['user']['wx']['update']['enabled'] = Request::bool('wxUpdate') ? 1 : 0;
    $settings['user']['wx']['sex']['enabled'] = Request::bool('sexData') ? 1 : 0;

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

    $protocol = Request::array('protocol');
    foreach (BlueToothProtocol::all() as $item) {
        Config::app("bluetooth.{$item['name']}.enabled", $protocol[$item['name']] ?? 0, true);
    }

    $themes = Request::array('theme');
    foreach (Theme::all() as $theme) {
        Config::app("theme.{$theme['name']}.enabled", $themes[$theme['name']] ?? 0, true);
    }

    $settings['device']['v-device']['enabled'] = Request::bool('vDevice') ? 1 : 0;
    $settings['goods']['lottery']['enabled'] = Request::bool('lotteryGoods') ? 1 : 0;
    $settings['goods']['ts']['enabled'] = Request::bool('tsGoods') ? 1 : 0;
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
    $settings['custom']['device']['briefPage']['enabled'] = Request::bool('deviceBriefPage') ? 1 : 0;
    $settings['custom']['smsPromo']['enabled'] = Request::bool('smsPromoEnabled') ? 1 : 0;
    $settings['custom']['team']['enabled'] = Request::bool('teamEnabled') ? 1 : 0;
    $settings['custom']['flashEgg']['enabled'] = Request::bool('flashEggEnabled') ? 1 : 0;
    $settings['custom']['promoter']['enabled'] = Request::bool('promoterEnabled') ? 1 : 0;
    $settings['custom']['GDCVMachine']['enabled'] = Request::bool('GDCVMachineEnabled') ? 1 : 0;
    $settings['custom']['MultiGoodsItem']['enabled'] = Request::bool('MultiGoodsItemEnabled') ? 1 : 0;
    $settings['custom']['getUserBalanceByMobile']['enabled'] = Request::bool('getUserBalanceByMobileEnabled') ? 1 : 0;
    $settings['custom']['TKPromoting']['enabled'] = Request::bool('TKPromotingEnabled') ? 1 : 0;
    $settings['custom']['longPressOrder']['enabled'] = Request::bool('longPressOrderEnabled') ? 1 : 0;
    $settings['custom']['keeper']['commissionLimit']['enabled'] = Request::bool('KeeperCommissionLimitEnabled') ? 1 : 0;
    $settings['custom']['keeper']['commissionOrderDistinguish']['enabled'] = Request::bool('KeeperCommissionOrderDistinguishEnabled') ? 1 : 0;
    $settings['custom']['device']['payConfig']['enabled'] = Request::bool('DevicePayConfigEnabled') ? 1 : 0;
    $settings['custom']['device']['laneQRCode']['enabled'] = Request::bool('DeviceLaneQRCodeEnabled') ? 1 : 0;
    $settings['custom']['puai']['enabled'] = Request::bool('puaiEnabled') ? 1 : 0;
    $settings['custom']['allCode']['enabled'] = Request::bool('allCodeEnabled') ? 1 : 0;
    $settings['custom']['appOnlineBonus']['enabled'] = Request::bool('appOnlineBonusEnabled') ? 1 : 0;
    $settings['custom']['deviceQoeBonus']['enabled'] = Request::bool('deviceQoeBonusEnabled') ? 1 : 0;

    Config::app('ad.sponsor.enabled', Request::bool('sponsorAd'), true);
    Config::app('misc.GoodsExpireAlert.enabled', Request::bool('GoodsExpireAlert') ? 1 : 0, true);

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
                'content' => "<?php\r\nrequire './framework/bootstrap.inc.php';\r\nheader('Location: ' . '$module_url');\r\nexit();",
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
        $settings['agent']['reg']['level'] = Request::str('agentRegLevel');
        $settings['agent']['reg']['commission_fee'] = round(Request::float('agentCommissionFee', 0, 2) * 100);
        $settings['agent']['reg']['commission_fee_type'] = Request::bool('feeType') ? 1 : 0;

        $settings['agent']['reg']['funcs'] = Helper::parseAgentFNsFromGPC();

        if ($settings['commission']['enabled']) {
            //佣金分享
            $settings['agent']['reg']['rel_gsp']['enabled'] = Request::bool('agentRelGsp') ? 1 : 0;
            $settings['agent']['reg']['gsp_mode_type'] = Request::str('gsp_mode_type', GSP::PERCENT);

            if ($settings['agent']['reg']['rel_gsp']['enabled']) {

                $rel_0 = max(0, Request::float('rel_gsp_level0', 0, 2) * 100);
                $rel_1 = max(0, Request::float('rel_gsp_level1', 0, 2) * 100);
                $rel_2 = max(0, Request::float('rel_gsp_level2', 0, 2) * 100);
                $rel_3 = max(0, Request::float('rel_gsp_level3', 0, 2) * 100);

                $gsp_mode_type = $settings['agent']['reg']['gsp_mode_type'];

                if (in_array($gsp_mode_type, [GSP::AMOUNT, GSP::AMOUNT_PER_GOODS], true)) {
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

    $settings['account']['log']['enabled'] = Request::bool('accountQueryLog') ? 1 : 0;

    $settings['misc']['pushAccountMsg_type'] = Request::trim('pushAccountMsg_type');
    $settings['misc']['pushAccountMsg_val'] = Request::trim('pushAccountMsg_val');
    $settings['misc']['pushAccountMsg_delay'] = Request::int('pushAccountMsg_delay');
    $settings['misc']['maxAccounts'] = Request::int('maxAccounts');
    $settings['misc']['accountsPromote'] = Request::bool('accountsPromote') ? 1 : 0;

    $settings['user']['maxTotalFree'] = Request::int('maxTotalFree');
    $settings['user']['maxFree'] = Request::int('maxFree');
    $settings['user']['freeCD'] = Request::int('freeCD');

    $settings['order']['retry']['last'] = Request::int('orderRetryLastTime');
    $settings['order']['retry']['max'] = Request::int('orderRetryMaxCount');

} elseif ($page == 'notice') {

    $data = [
        [
            'title' => '设备上线',
            'event' => 'deviceOnline',
            'key' => 'device.online',
            'tpl_short_id' => '43264',
            'tpl_params' => ['设备名称', '设备编号', '设备位置', '上线时间'],
        ],
        [
            'title' => '设备离线',
            'event' => 'deviceOffline',
            'key' => 'device.offline',
            'tpl_short_id' => '43110',
            'tpl_params' => ['设备名称', '设备编号', '设备位置', '离线时间'],
        ],
        [
            'title' => '设备故障',
            'event' => 'deviceError',
            'key' => 'device.error',
            'tpl_short_id' => '43716',
            'tpl_params' => ['设备名称', 'IMEI号', '设备位置', '故障类型', '故障时间'],
        ],
        [
            'title' => '设备电量低',
            'event' => 'deviceLowBattery',
            'key' => 'device.low_battery',
            'tpl_short_id' => '47059',
            'tpl_params' => ['设备名称', '设备编号', '设备位置', '设备状态'],
        ],
        [
            'title' => '设备缺货',
            'event' => 'deviceLowRemain',
            'key' => 'device.low_remain',
            'tpl_short_id' => '44162',
            'tpl_params' => ['设备名称', '设备编号', '设备位置', '设备状态'],
        ],
        [
            'title' => '设备自动售卖成功通知',
            'event' => 'orderSucceed',
            'key' => 'order.succeed',
            'tpl_short_id' => '51278',
            'tpl_params' => ['订单编号', '设备编号', '商品名称', '价格', '时间'],
        ],
        [
            'title' => '售货机出货失败通知',
            'event' => 'orderFailed',
            'key' => 'order.failed',
            'tpl_short_id' => '51500',
            'tpl_params' => ['订单编号', '设备编号', '商品名称', '出货时间'],
        ],
    ];

    $result = Wx::getAllTemplate();
    if (is_error($result)) {
        Log::error('settings', [
            'error' => '获取模板列表失败',
            'result' => $result,
        ]);
    } else {
        $template_list = $result['template_list'];

        $existsFN = function ($template_id) use ($template_list) {
            foreach ($template_list as $template) {
                if ($template['template_id'] == $template_id) {
                    return true;
                }
            }

            return false;
        };

        $config = Config::WxPushMessage('config', []);

        foreach ($data as $item) {
            $conf = getArray($config, $item['key'], []);
            $conf['enabled'] = Request::bool($item['event']);
            if ($conf['enabled']) {
                if (empty($conf['tpl_id']) || !$existsFN($conf['tpl_id'])) {
                    $res = Wx::addTemplate($item['tpl_short_id'], $item['tpl_params']);
                    if (!is_error($res)) {
                        $conf['tpl_id'] = $res['template_id'];
                    } else {
                        $conf['tpl_id'] = '';
                        Log::error('settings', [
                            'item' => $item,
                            'result' => $res,
                        ]);
                    }
                }
            }

            setArray($config, $item['key'], $conf);
        }

        $data = [
            [
                'title' => '代理审核',
                'event' => 'authorizedAdminUser',
                'key' => 'sys.auth',
                'user_id' => Request::int('authorizedAdminUser'),
            ],
            [
                'title' => '提现审核',
                'event' => 'withdrawAdminUser',
                'key' => 'sys.withdraw',
                'user_id' => Request::int('withdrawAdminUser'),
            ],
            [
                'title' => '广告审核',
                'event' => 'reviewAdminUser',
                'key' => 'sys.review',
                'user_id' => Request::int('reviewAdminUser'),
            ],
        ];

        $template_reg = false;

        foreach ($data as $item) {
            $conf = getArray($config, $item['key'], []);
            if ($item['user_id']) {
                $template_reg = true;
                $user = User::get($item['user_id']);
                if ($user) {
                    $conf['user'] = [
                        'id' => $user->getId(),
                        'name' => $user->getName(),
                    ];
                    $mobile = $user->getMobile();
                    if ($mobile) {
                        $conf['user']['name'] .= " ( $mobile )";
                    }
                } else {
                    $conf['user'] = [];
                }
            } else {
                $conf['user'] = [];
            }

            setArray($config, $item['key'], $conf);
        }

        $tpl_id = getArray($config, 'sys.tpl_id', '');

        if ($template_reg && (empty($tpl_id) || !$existsFN($tpl_id))) {

            $tpl_short_id = '43719';
            $tpl_params = ['工单名称', '工单状态', '发起人员', '用户手机号', '创建时间'];

            $res = Wx::addTemplate($tpl_short_id, $tpl_params);
            if (!is_error($res)) {
                setArray($config, 'sys.tpl_id', $res['template_id']);
            } else {
                setArray($config, 'sys.tpl_id', '');
                Log::error('settings', [
                    'item' => $item,
                    'result' => $res,
                ]);
            }
        }

        Config::WxPushMessage('config', $config, true);
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

    if (Request::bool('lcsw')) {
        $data = [
            'merchant_no' => Request::trim('merchant_no'),
            'terminal_id' => Request::trim('terminal_id'),
            'access_token' => Request::trim('access_token'),
            'app' => [
                'wx' => [
                    'h5' => Request::bool('lcswWxH5'),
                    'mini_app' => Request::bool('lcswWxMiniApp'),
                ],
                'ali' => Request::bool('lcswAli'),
            ],
        ];
        $res = PaymentConfig::createOrUpdateDefaultByName(Pay::LCSW, $data);
        if (is_error($res)) {
            Log::error('settings', [
                'error' => $res,
                'data' => $data,
            ]);
        }
    } else {
        PaymentConfig::removeDefaultByName(Pay::LCSW);
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
                PaymentConfig::createOrUpdateDefaultByName(Pay::SQB, [
                    'sn' => $result['terminal_sn'],
                    'key' => $result['terminal_key'],
                    'title' => $result['store_name'],
                    'app' => [
                        'wx' => [
                            'h5' => Request::bool('SQBWxH5'),
                            'mini_app' => Request::bool('SQBWxMiniApp'),
                        ],
                        'ali' => Request::bool('SQBAli'),
                    ],
                ]);
            }
        } else {
            $config = PaymentConfig::getDefaultByName(Pay::SQB);
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
        PaymentConfig::removeDefaultByName(Pay::SQB);
    }

    if (Request::bool('wx')) {
        $data = [
            'appid' => Request::trim('wxAppID'),
            'wxappid' => Request::trim('wxxAppID'),
            'key' => Request::trim('wxApiKey'),
            'mch_id' => Request::trim('wxMCHID'),
            'sub_mch_id' => Request::trim('wxSubMCHID'),
            'pem' => [
                'cert' => Request::trim('certPEM'),
                'key' => Request::trim('keyPEM'),
            ],
            'app' => [
                'wx' => [
                    'h5' => true,
                    'mini_app' => true,
                ],
            ],
        ];

        $res = PaymentConfig::createOrUpdateDefaultByName(Pay::WX, $data);
        if (is_error($res)) {
            Log::error('settings', [
                'error' => $res,
                'data' => $data,
            ]);
        }

        if (Request::bool('v3Serial')) {
            $data = [
                'appid' => Request::trim('wxAppID'),
                'wxappid' => Request::trim('wxxAppID'),
                'key' => Request::trim('wxApiV3Key'),
                'serial' => Request::trim('v3Serial'),
                'mch_id' => Request::trim('wxMCHID'),
                'sub_mch_id' => Request::trim('wxSubMCHID'),
                'pem' => [
                    'key' => Request::trim('V3key'),
                ],
                'app' => [
                    'wx' => [
                        'h5' => true,
                        'mini_app' => true,
                    ],
                ],
            ];

            $config = PaymentConfig::getDefaultByName(Pay::WX_V3);
            if ($config) {
                $data['pem']['cert'] = $config->getExtraData('pem.cert', []);
                $config->setExtraData($data);
                $config->save();
            } else {
                PaymentConfig::create([
                    'agent_id' => 0,
                    'name' => Pay::WX_V3,
                    'extra' => $data,
                ]);
            }
        } else {
            PaymentConfig::removeDefaultByName(Pay::WX_V3);
        }
    } else {
        PaymentConfig::removeDefaultByName(Pay::WX);
        PaymentConfig::removeDefaultByName(Pay::WX_V3);
    }

    $settings['ali']['appid'] = Request::trim('ali_appid');
    $settings['ali']['pubkey'] = Request::trim('ali_pubkey');
    $settings['ali']['prikey'] = Request::trim('ali_prikey');

    $settings['alixapp']['id'] = Request::trim('alixapp_id');
    $settings['alixapp']['pubkey'] = Request::trim('alixapp_pubkey');
    $settings['alixapp']['prikey'] = Request::trim('alixapp_prikey');

} elseif ($page == 'data_vw') {
    $db_arr = [];

    $res = m('data_vw')->findAll();

    /** @var data_vwModelObj $item */
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

    foreach ($need_inserted_arr as $key => $val) {
        m('data_vw')->create([
            'k' => $key,
            'v' => $val,
            'createtime' => time(),
        ]);
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

        $promote_bonus_opts = Request::array('accountPromoteBonusOption');
        foreach (['third_platform', 'account', 'video', 'wxapp', 'douyin'] as $name) {
            Config::balance("account.promote_bonus.$name", in_array($name, $promote_bonus_opts, true) ? 1 : 0, true);
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
    Response::toast('设置保存成功！', Util::url('settings', ['page' => $page]), 'success');
}

Response::toast('设置保存失败！', Util::url('settings', ['page' => $page]), 'error');