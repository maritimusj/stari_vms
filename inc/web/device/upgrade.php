<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\versionModelObj;

$version_id = request::int('version');

$device = Device::find(request::trim('id'), ['id', 'imei']);
if (empty($device)) {
    JSON::fail('找不到这个设备！');
}

/** @var versionModelObj $version */
$version = m('version')->findOne(We7::uniacid(['id' => $version_id]));
if (empty($version) || empty($version->getUrl())) {
    JSON::fail('版本信息不正确！');
}

$res = $device->upgradeApk($version->getTitle(), $version->getVersion(), $version->getUrl());
if ($res) {
    JSON::success("已通知设备下载更新！\r\n版本：{$version->getVersion()}\r\n网址：{$version->getUrl()}");
} else {
    JSON::fail('通知更新失败！');
}