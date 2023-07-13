<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\deviceModelObj;

$id = Request::int('id');
if ($id) {
    /** @var deviceModelObj $device */
    $device = Device::get($id);
    if ($device) {
        $res = DeviceUtil::test(null, $device);
        if (is_error($res)) {
            JSON::fail($res);
        }
        JSON::success('出货成功！');
    }
}

JSON::fail('找不到设备！');