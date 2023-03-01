<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

if ($id > 0) {
    $adv = Advertising::get($id);
}

if (empty($adv)) {

    JSON::fail('找不到这个广告！');

} elseif (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {

    $assign_data = $adv->settings('assigned', []);
    if ($assign_data) {
        if (Advertising::notifyAll([], $assign_data)) {
            JSON::success('已通知设备更新！');
        }
    }
}

JSON::fail('操作失败！');