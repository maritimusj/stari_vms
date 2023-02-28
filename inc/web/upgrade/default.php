<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\versionModelObj;

$device_id = request::int('id');
$all = [];
/** @var versionModelObj $entry */
foreach (m('version')->findAll(We7::uniacid([])) as $entry) {
    $all[] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'version' => $entry->getVersion(),
        'url' => $entry->getUrl(),
        'createtime' => $entry->getCreatetime(),
    ];
}

if (request::is_ajax()) {
    $device_name = '';
    $lastUpgradeInfo = [];
    if ($device_id) {
        $device = Device::get($device_id);
        if ($device) {
            $lastUpgradeInfo = $device->getLastApkUpgrade();
            $device_name = $device->getName();
        }
    }

    $content = app()->fetchTemplate(
        'web/version/list',
        [
            'lastUpgradeInfo' => $lastUpgradeInfo,
            'all' => $all,
            'device_id' => $device_id,
        ]
    );

    JSON::success(['title' => "请选择要升级的版本(设备：$device_name)", 'content' => $content]);
}

app()->showTemplate('web/version/upgrade', [
    'deviceid' => $device_id,
    'all' => $all,
]);