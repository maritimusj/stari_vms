<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
if ($id) {
    $device = Device::get($id);
    if ($device && $device->resetLock()) {
        JSON::success('锁定已解除！');
    }
}

JSON::success();