<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('device');

$settings = settings();

/**
 * 初始化设置页面数据
 */
$tpl_data['navs'] = Util::getSettingsNavs();

if (!(array_key_exists($op, $tpl_data['navs']) || $op == 'ctrl')) {
    Util::itoast('找不到这个配置页面！', $this->createWebUrl('settings'), 'error');
}

$tpl_data['op'] = $op;
$tpl_data['settings'] = $settings;

app()->showTemplate("web/settings/$op", $tpl_data);
