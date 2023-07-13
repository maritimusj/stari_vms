<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$back_url = $this->createWebUrl('settings', ['page' => 'upgrade']);

$data = HttpUtil::get(UPGRADE_URL . '/?op=exec');
$res = json_decode($data, true);
if ($res && $res['status']) {
    if (!Migrate::detect(true)) {
        Util::itoast('更新成功！', $back_url, 'success');
    }
}

Util::itoast('更新失败！', $back_url, 'success');