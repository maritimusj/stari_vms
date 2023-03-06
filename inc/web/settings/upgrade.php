<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$back_url = $this->createWebUrl('settings', ['page' => 'upgrade']);

$data = Util::get(UPGRADE_URL);
if (empty($data)) {
    Util::itoast('检查更新失败！', $back_url, 'success');
}

$res = json_decode($data, true);
if (!$res) {
    Util::itoast('检查更新失败！', $back_url, 'success');
}

if (!$res['status']) {
    Util::itoast('暂无无法检查升级！', $back_url, 'success');
}

if (empty($res['data']['download'])) {
    Util::itoast('暂时没有任何文件需要更新！', $back_url, 'success');
}

$data = Util::get(UPGRADE_URL . '/?op=exec');
$res = json_decode($data, true);
if ($res && $res['status']) {
    if (!Migrate::detect(true)) {
        Util::itoast('更新成功！', $back_url, 'success');
    }
}

Util::itoast('更新失败！', $back_url, 'success');