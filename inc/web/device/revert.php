<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$confirm_code = Request::trim('code');
$device = Device::get(Request::int('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if ($device->getShadowId() != $confirm_code) {
    JSON::fail('操作失败，确认码不正确！(注意大小写)');
}

$res = DBUtil::transactionDo(
    function () use ($device) {
        return $device->resetAllData() ? true : err('清除失败！');
    }
);

if (is_error($res)) {
    JSON::fail($res);
}

JSON::success('重置成功！');