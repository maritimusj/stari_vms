<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data['navs'] = Util::getSettingsNavs();
$tpl_data['bonus_url'] = Util::murl('bonus');
$tpl_data['api_url'] = Util::murl('user');
$tpl_data['app_key'] = Config::balance('app.key');
$tpl_data['notify_url'] = Config::balance('app.notify_url');

$tpl_data['op'] = 'balance';
$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/balance', $tpl_data);