<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data['navs'] = Util::getSettingsNavs();

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

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/misc', $tpl_data);