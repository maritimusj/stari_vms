<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Apk;
use zovye\domain\Device;
use zovye\model\versionModelObj;

$device_id = Request::int('id');
$all = [];
/** @var versionModelObj $entry */
foreach (Apk::query()->findAll() as $entry) {
    $all[] = [
        'id' => $entry->getId(),
        'title' => $entry->getTitle(),
        'version' => $entry->getVersion(),
        'url' => $entry->getUrl(),
        'createtime' => $entry->getCreatetime(),
    ];
}

if (Request::is_ajax()) {
    $device_name = '';
    $lastUpgradeInfo = [];
    if ($device_id) {
        $device = Device::get($device_id);
        if ($device) {
            $lastUpgradeInfo = $device->getLastApkUpgrade();
            $device_name = $device->getName();
        }
    }

    Response::templateJSON(
        'web/version/list',
        "请选择要升级的版本(设备：$device_name)",
        [
            'lastUpgradeInfo' => $lastUpgradeInfo,
            'all' => $all,
            'device_id' => $device_id,
        ]
    );
}

Response::showTemplate('web/version/upgrade', [
    'deviceid' => $device_id,
    'all' => $all,
]);