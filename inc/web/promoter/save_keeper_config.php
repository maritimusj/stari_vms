<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Keeper;

defined('IN_IA') or exit('Access Denied');

$keeper_id = Request::int('id');

$keeper = Keeper::get($keeper_id);
if (empty($keeper)) {
    JSON::fail('找不到这个运营人员！');
}

$keeper->updateSettings('notice', [
    'order' => [
        'succeed' => Request::bool('orderSucceed') ? 1 : 0,
        'failed' => Request::bool('orderFailed') ? 1 : 0,
    ],
    'device' => [
        'online' => Request::bool('deviceOnline') ? 1 : 0,
        'offline' => Request::bool('deviceOffline') ? 1 : 0,
        'error' => Request::bool('deviceError') ? 1 : 0,
        'low_battery' => Request::bool('deviceLowBattery') ? 1 : 0,
        'low_remain' => Request::bool('deviceLowRemain') ? 1 : 0,
    ]
]);

JSON::success([
    'msg' => '保存成功！',
]);
