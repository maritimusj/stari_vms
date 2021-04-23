<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\versionModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op == 'apk') {

    $title = request::trim('title');
    $url = request::trim('url');
    $version = request::trim('version');

    if ($url && $version) {
        if (m('version')->create(
            We7::uniacid(
                [
                    'title' => $title,
                    'url' => $url,
                    'version' => $version,
                ]
            )
        )) {
            Util::itoast('保存成功！', $this->createWebUrl('upgrade'), 'success');
        }
    }

    Util::itoast('保存失败！', $this->createWebUrl('upgrade'), 'error');

} elseif ($op == 'remove') {

    $id = request::int('id');
    if ($id) {
        $v = m('version')->findOne(We7::uniacid(['id' => $id]));
        if ($v && $v->destroy()) {
            exit('ok');
        }
    }

    exit('fail');

} else {

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

        $content = $this->fetchTemplate(
            'web/version/list',
            [
                'lastUpgradeInfo' => $lastUpgradeInfo,
                'all' => $all,
                'device_id' => $device_id,
            ]
        );

        JSON::success(['title' => "请选择要升级的版本(设备：{$device_name})", 'content' => $content]);
    }

    $this->showTemplate('web/version/upgrade', [
        'deviceid' => $device_id,
        'all' => $all,
    ]);
}
