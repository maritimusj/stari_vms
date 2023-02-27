<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
if ($id) {
    $device = Device::get($id);
    if ($device && $device->updateSettings('extra.isDown', Device::STATUS_NORMAL) && $device->save()) {
        JSON::success('维护状态已取消！');
    }
}

JSON::success();