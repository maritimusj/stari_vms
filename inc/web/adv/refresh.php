<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Advertising;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$adv = Advertising::get($id);

if (empty($adv)) {
    JSON::fail('找不到这个广告！');
}

if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV], true)) {

    $assign_data = $adv->settings('assigned', []);
    if ($assign_data) {
        if (Advertising::notifyAll([], $assign_data)) {
            JSON::success('已通知设备更新！');
        }
    }
}

JSON::fail('操作失败！');