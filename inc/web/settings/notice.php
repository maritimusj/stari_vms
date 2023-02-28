<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data['navs'] = Util::getSettingsNavs();

$settings = settings();

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

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/user', $tpl_data);