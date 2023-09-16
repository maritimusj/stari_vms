<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\domain\Device;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $device = Device::get($id);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }
    
    $device->setMaintenance(Device::STATUS_NORMAL);
    if (!$device->save()) {
        JSON::fail('保存数据失败！');
    }

    JSON::success('维护状态已取消！');
}

JSON::success();