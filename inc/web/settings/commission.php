<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

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