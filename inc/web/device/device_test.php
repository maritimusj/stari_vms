<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Device;
use zovye\model\deviceModelObj;
use zovye\util\DeviceUtil;

$id = Request::int('id');
if ($id > 0) {
    /** @var deviceModelObj $device */
    $device = Device::get($id);
    if ($device) {
        $res = DeviceUtil::test($device);
        if (is_error($res)) {
            JSON::fail($res);
        }
        JSON::success('出货成功！');
    }
}

JSON::fail('找不到设备！');