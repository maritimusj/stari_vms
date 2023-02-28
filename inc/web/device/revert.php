<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$confirm_code = request::trim('code');
$device = Device::get(request('id'));
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

if ($device->getShadowId() != $confirm_code) {
    JSON::fail('操作失败，确认码不正确！(注意大小写)');
}

$res = Util::transactionDo(
    function () use ($device) {
        return $device->resetAllData() ? true : error(State::FAIL, '清除失败！');
    }
);

Util::resultJSON(!is_error($res), ['msg' => is_error($res) ? $res['message'] : '重置成功！']);