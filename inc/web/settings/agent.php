<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$settings = settings();

$tpl_data['navs'] = Util::getSettingsNavs();

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

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/agent', $tpl_data);