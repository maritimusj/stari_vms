<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');

$ad = Advertising::get($id);
if (empty($ad)) {
    JSON::fail('找不到这个广告！');
}

$state = $ad->getState() == Advertising::NORMAL ? Advertising::BANNED : Advertising::NORMAL;
if ($ad->setState($state) && Advertising::update($ad)) {
    if (in_array($ad->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
        //通知设备更新屏幕广告
        $assign_data = $ad->settings('assigned', []);
        Advertising::notifyAll($assign_data);
    }

    JSON::success([
        'msg' => $ad->getState() == Advertising::NORMAL ? '已启用' : '已禁用',
        'state' => intval($ad->getState()),
    ]);
}

JSON::fail('操作失败！');