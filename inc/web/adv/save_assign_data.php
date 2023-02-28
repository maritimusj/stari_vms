<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$data = Request::is_string('data') ? json_decode(htmlspecialchars_decode(Request::str('data')), true) : request(
    'data'
);

$adv = Advertising::get($id);
if (empty($adv)) {
    JSON::fail('找不到这个广告，无法保存！');
}

$origin_data = $adv->settings('assigned', []);
if ($adv->updateSettings('assigned', $data) && Advertising::update($adv)) {
    if (in_array($adv->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
        if (Advertising::notifyAll($origin_data, $data)) {
            JSON::success('设置已经保存成功，已通知设备更新！');
        } else {
            JSON::success('设置已经保存成功，通知设备失败！');
        }
    } else {
        JSON::success('设置已经保存成功！');
    }
}

JSON::fail('保存失败！');