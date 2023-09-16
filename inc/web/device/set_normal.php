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
    if ($device) {
        $device->setMaintenance(Device::STATUS_NORMAL);
        if ($device->save()) {
            JSON::success('维护状态已取消！');
        }
    }
}

JSON::success();