<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$settings = settings();

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

$tpl_data['settings'] = $settings;

app()->showTemplate('web/settings/account', $tpl_data);