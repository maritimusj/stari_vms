<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
if ($id) {
    $device = Device::get($id);
    if ($device && $device->confirmLAC()) {
        JSON::success('已确认！');
    }
}

JSON::success();