<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(request('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

$firstMsg = $device->get('firstMsgStatistic', []);

JSON::success(['first' => $firstMsg]);