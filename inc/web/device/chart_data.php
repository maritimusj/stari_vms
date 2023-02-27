<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$device = Device::get(request('id'));
$firstMsg = $device->get('firstMsgStatistic', []);

JSON::success(['first' => $firstMsg]);