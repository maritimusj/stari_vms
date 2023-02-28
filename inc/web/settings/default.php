<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;

$settings = settings();

$tpl_data['navs'] = Util::getSettingsNavs();

$page = request::trim('page', 'device');

if (!(array_key_exists($page, $tpl_data['navs']) || $page == 'ctrl')) {
    Util::itoast('找不到这个配置页面！', $this->createWebUrl('settings'), 'error');
}

if ($page == 'device') {

    $tpl_data['lbsKey'] = settings('user.location.appkey', DEFAULT_LBS_KEY);
    $tpl_data['loc_url'] = Util::murl('util');
    $tpl_data['test_url'] = Util::murl('testing');
    $tpl_data['theme'] = settings('device.get.theme');
    $tpl_data['themes'] = Theme::all();
    $tpl_data['lbs_limits'] = Config::location('tencent.lbs.limits', []);

} elseif ($page == 'agent') {

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

    $tpl_data['goods'] = $goods ?? [];
    $tpl_data['agreement'] = Config::agent('agreement', []);

} elseif ($page == 'account') {

    if (App::isWxPlatformEnabled()) {
        if (empty($settings['account']['wx']['platform']['config']['token']) || empty($settings['account']['wx']['platform']['config']['key'])) {
    
            $settings['account']['wx']['platform']['config']['token'] = Util::random(32);
            $settings['account']['wx']['platform']['config']['key'] = Util::random(43);
    
            updateSettings('account.wx.platform.config', $settings['account']['wx']['platform']['config']);
        }
    
        $tpl_data['auth_notify_url'] = Util::murl('wxplatform', ['op' => WxPlatform::AUTH_NOTIFY]);
        $tpl_data['msg_notify_url'] = Util::murl('wxplatform', ['op' => WxPlatform::AUTHORIZER_EVENT]).'&appid=/$APPID$';
    }
    
    if (App::isDouyinEnabled()) {
        $tpl_data['douyin'] = Config::douyin('client', []);
    }
    
    if (App::isCZTVEnabled()) {
        $tpl_data['cztv'] = Config::cztv('client', []);
    }

} elseif ($page == 'balance') {

    $tpl_data['navs'] = Util::getSettingsNavs();
    $tpl_data['bonus_url'] = Util::murl('bonus');
    $tpl_data['api_url'] = Util::murl('user');
    $tpl_data['app_key'] = Config::balance('app.key');
    $tpl_data['notify_url'] = Config::balance('app.notify_url');

} elseif ($page == 'commission') {

    $tpl_data['pem'] = empty($settings['pem']) ? ['key' => '', 'cert' => ''] : unserialize($settings['pem']);

    if (!isset($settings['commission']['withdraw']['fee']['permille'])) {
        $settings['commission']['withdraw']['fee']['permille'] = min(
            1000,
            intval($settings['commission']['withdraw']['fee']['percent'] * 10)
        );
    }

    $settings['commission']['withdraw']['min'] = $settings['commission']['withdraw']['min'] / 100;
    $settings['commission']['withdraw']['max'] = $settings['commission']['withdraw']['max'] / 100;

    $tpl_data['withdraw_url'] = Util::murl('withdraw');

} elseif ($page == 'user') {

    if (App::isIDCardVerifyEnabled()) {
        $res = CtrlServ::getV2('idcard/balance');
    
        if (!empty($res) && $res['status']) {
            $tpl_data['idcard_balance'] = $res['data']['balance'];
        } else {
            $tpl_data['idcard_balance'] = $res['data']['msg'];
        }
    }

} elseif ($page == 'wxapp') {

    if (App::isCustomWxAppEnabled()) {
        $query = WxApp::query();
        $query->orderBy('id desc');
    
        $list = [];
        /** @var wx_appModelObj $app */
        foreach ($query->findAll() as $app) {
            $data = [
                'id' => $app->getId(),
                'name' => $app->getName(),
                'key' => $app->getKey(),
                'secret' => $app->getSecret(),
                'createtime_formatted' => date('Y-m-d H:i:s', $app->getCreatetime()),
            ];
            $list[] = $data;
        }
    
        $tpl_data['list'] = $list;
    }
    
    if (App::isBalanceEnabled()) {
        $tpl_data['advs_position'] = [
            'banner' => [
                'id' => 1,
                'title' => 'Banner广告',
                'description' => '灵活性较高，适用于用户停留较久或访问频繁等场景',
            ],
            'reward' => [
                'id' => 2,
                'title' => '激励广告',
                'description' => '用户观看广告获得奖励，适用于道具解锁或获得积分等场景',
                'balance' => true,
            ],
            'interstitial' => [
                'id' => 3,
                'title' => '插屏广告',
                'description' => '弹出展示广告，适用于页面切换或回合结束等场景',
            ],
            'video' => [
                'id' => 4,
                'title' => '视频广告',
                'description' => '适用于信息流场景或固定位置，展示自动播放的视频广告',
            ],
        ];
    
        $tpl_data['advsID'] = Config::app('wxapp.advs', []);
        $tpl_data['notify_url'] = Util::murl('wxnotify');

        $config = Config::app('wxapp.message-push', []);
        if (empty($config['token'])) {
            $config['token'] = Util::random(32);
        }
    
        $tpl_data['config'] = $config;
    }
} elseif ($page == 'data_view') {

    $goods = [
        'g1' => '商品一',
        'g2' => '商品二',
        'g3' => '商品三',
        'g4' => '商品四',
        'g5' => '商品五',
        'g6' => '商品六',
        'g7' => '商品七',
        'g8' => '商品八',
        'g9' => '商品九',
        'g10' => '商品十',
    ];
    
    $provinces = Util::getProvinceList();
    
    $tpl_data['goods'] = $goods;
    $tpl_data['provinces'] = $provinces;
    
    $keys = [
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

} elseif ($page == 'ctrl') {
    
    $tpl_data['navs'] = Util::getSettingsNavs();

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
        } else {
            if ($data['startTime']) {
                $tpl_data['formatted_duration'] = Util::getFormattedPeriod($data['startTime']);
            }
        }
    
        if ($data['now']) {
            $tpl_data['formatted_now'] = (new DateTime())->setTimestamp($data['now'])->format("Y-m-d H:i:s");
        }
        $tpl_data['queue'] = Config::app('queue', []);
    }
    
    if (App::isChargingDeviceEnabled()) {
        $tpl_data['charging'] = [
            'server' => Config::charging('server', []),
        ];
    
        $res = ChargingServ::GetVersion();
        if (is_error($res)) {
            $tpl_data['charging']['server']['version'] = 'n/a';
        } else {
            $tpl_data['charging']['server']['version'] = $res['version'];
            $tpl_data['charging']['server']['build'] = $res['build'];
        }
    }
    
    $tpl_data['migrate'] = Migrate::detect();
    
} elseif ($page == 'notice') {

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
} elseif ($page == 'upgrade') {

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
                        $data = Util::get(UPGRADE_URL.'/?op=exec');
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
                            $local_file = MODULE_ROOT.$filename;
                            if (file_exists($local_file)) {
                                $stats = stat($local_file);
                                if ($stats) {
                                    $fi['size'] = is_dir($local_file) ? '<文件夹>' : $stats[7];
                                    $fi['createtime'] = (new DateTime("@$stats[9]"))->format('Y-m-d H:i:s');
                                }
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
                $tpl_data['upgrade']['error'] = empty($res['data']['message']) ? '暂无无法检查升级！' : strval(
                    $res['data']['message']
                );
            }
        } else {
            $tpl_data['upgrade']['error'] = '检查更新失败！';
        }
    }
} elseif ($page == 'misc') {

    $tpl_data['media'] = ['type' => settings('misc.pushAccountMsg_type'), 'val' => settings('misc.pushAccountMsg_val')];
    We7::load()->model('mc');
    $tpl_data['credit_types'] = We7::mc_credit_types();

    $tpl_data['data_url'] = Util::murl('data');
    $tpl_data['device_brief_url'] = Util::murl('brief');
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

    $tpl_data['notify_app_key'] = Config::notify('order.key', Util::random(16));
    $tpl_data['orderNotifyFree'] = Config::notify('order.f', true);
    $tpl_data['orderNotifyPay'] = Config::notify('order.p', true);
    $tpl_data['order_notify_url'] = Config::notify('order.url', '');

    $tpl_data['inventory_access_key'] = Config::notify('inventory.key', Util::random(16));
    $tpl_data['inventory_api_url'] = Util::murl('inventory');

}

$tpl_data['page'] = $page;
$tpl_data['settings'] = $settings;
app()->showTemplate("web/settings/$page", $tpl_data);