<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Advertising;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
$data = Request::is_string('data') ? json_decode(htmlspecialchars_decode(Request::str('data')), true) : request(
    'data'
);

$ad = Advertising::get($id);
if (empty($ad)) {
    JSON::fail('找不到这个广告，无法保存！');
}

$origin_data = $ad->settings('assigned', []);
if ($ad->updateSettings('assigned', $data) && Advertising::update($ad)) {
    if (in_array($ad->getType(), [Advertising::SCREEN, Advertising::SCREEN_NAV])) {
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