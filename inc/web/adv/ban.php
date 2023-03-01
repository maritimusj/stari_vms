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
    if (empty($adv)) {
        JSON::fail('找不到这个广告！');
    }

    $state = $adv->getState() == Advertising::NORMAL ? Advertising::BANNED : Advertising::NORMAL;
    if ($adv->setState($state) && Advertising::update($adv)) {
        if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
            //通知设备更新屏幕广告
            $assign_data = $adv->settings('assigned', []);
            Advertising::notifyAll($assign_data, []);
        }

        JSON::success([
            'msg' => $adv->getState() == Advertising::NORMAL ? '已启用' : '已禁用',
            'state' => intval($adv->getState()),
        ]);
    }
}

JSON::fail('操作失败！');