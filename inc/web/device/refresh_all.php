<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Advertising;
use zovye\domain\Device;
use zovye\model\deviceModelObj;

defined('IN_IA') or exit('Access Denied');

$fn = Request::trim('fn', 'app');
if (empty($fn) || $fn == 'app') {
    if (Advertising::notifyAll(['all' => 1])) {
        JSON::success('已通知有设备更新！');
    }
} elseif ($fn == 'mcb') {
    /** @var deviceModelObj $device */
    foreach (Device::query()->findAll() as $device) {
        $device->reportMcbStatus();
    }
}

JSON::fail('通知失败！');