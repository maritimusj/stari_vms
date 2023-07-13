<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use RuntimeException;

$id = Request::int('id');
$device = Device::get($id);
if (empty($device)) {
    Response::toast('找不到这个设备！', $this->createWebUrl('device'), 'error');
}

if (!$device->payloadLockAcquire(3)) {
    Response::toast('设备正忙，请稍后再试！', $this->createWebUrl('device'), 'error');
}

$result = DBUtil::transactionDo(function () use ($device) {
    if (!Request::isset('lane') || Request::str('lane') == 'all') {
        $data = [];
    } else {
        $data = [
            Request::int('lane') => 0,
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
    Response::toast($result['message'], $this->createWebUrl('device'), 'error');
}

$device->updateAppRemain();
Response::toast('商品数量重置成功！', $this->createWebUrl('device'), 'success');