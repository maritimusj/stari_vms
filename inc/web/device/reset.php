<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use RuntimeException;

$id = request::int('id');
$device = Device::get($id);
if (empty($device)) {
    Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
}

if (!$device->payloadLockAcquire(3)) {
    Util::itoast('设备正忙，请稍后再试！', $this->createWebUrl('device'), 'error');
}

$result = Util::transactionDo(function () use ($device) {
    if (!request::isset('lane') || request::str('lane') == 'all') {
        $data = [];
    } else {
        $data = [
            request::int('lane') => 0,
        ];
    }
    $res = $device->resetPayload($data, '管理员重置商品数量');
    if (is_error($res)) {
        throw new RuntimeException('保存库存失败！');
    }
    if (!$device->save()) {
        throw new RuntimeException('保存数据失败！');
    }

    return true;
});

if (is_error($result)) {
    Util::itoast($result['message'], $this->createWebUrl('device'), 'error');
}

$device->updateAppRemain();
Util::itoast('商品数量重置成功！', $this->createWebUrl('device'), 'success');