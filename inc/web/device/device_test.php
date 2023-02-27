<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\deviceModelObj;

$id = request::int('id');
if ($id) {
    /** @var deviceModelObj $device */
    $device = Device::get($id);
    if ($device) {
        $res = Util::deviceTest(null, $device);
        if (is_error($res)) {
            JSON::fail($res);
        }
        JSON::success('出货成功！');
    }
}

JSON::fail('找不到设备！');