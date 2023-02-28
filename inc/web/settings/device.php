<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data['navs'] = Util::getSettingsNavs();

$tpl_data['lbsKey'] = settings('user.location.appkey', DEFAULT_LBS_KEY);
$tpl_data['loc_url'] = Util::murl('util');
$tpl_data['test_url'] = Util::murl('testing');
$tpl_data['theme'] = settings('device.get.theme');
$tpl_data['themes'] = Theme::all();
$tpl_data['lbs_limits'] = Config::location('tencent.lbs.limits', []);

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/device', $tpl_data);