<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device_type = DeviceTypes::get(request('id'));
if (empty($device_type)) {
    JSON::fail('找不到这个设备型号！');
}

$res = Util::transactionDo(
    function () use ($device_type) {
        $type_id = $device_type->getId();
        if ($device_type->destroy()) {
            if (Device::removeDeviceType($type_id)) {
                return true;
            }
        }

        return err('失败');
    }
);

if (is_error($res)) {
    JSON::fail('删除失败！');
}

JSON::success('删除成功！');