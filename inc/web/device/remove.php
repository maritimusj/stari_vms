<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$device = Device::get(Request::int('id'));
if (empty($device)) {
    Util::itoast('删除失败！', $this->createWebUrl('device'), 'error');
}

We7::load()->func('file');
We7::file_remote_delete($device->getQrcode());

$device->remove('assigned');
$device->remove('adsData');
$device->remove('accountsData');
$device->remove('lastErrorNotify');
$device->remove('lastRemainWarning');
$device->remove('fakeQrcodeData');
$device->remove('lastApkUpdate');
$device->remove('firstMsgStatistic');
$device->remove('location');
$device->remove('statsData');
$device->remove('lastErrorData');
$device->remove('extra');

if ($device->isChargingDevice()) {
    ChargingNowData::removeAllByDevice($device);
}

//删除相关套餐
foreach (Package::query(['device_id' => $device->getId()])->findAll() as $entry) {
    $entry->destroy();
}

//通知实体设备
$device->appNotify('update');

$device->destroy();

Util::itoast('删除成功！', $this->createWebUrl('device'), 'success');