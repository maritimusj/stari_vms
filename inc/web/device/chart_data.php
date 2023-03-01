<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(request('id'));
$firstMsg = $device->get('firstMsgStatistic', []);

JSON::success(['first' => $firstMsg]);