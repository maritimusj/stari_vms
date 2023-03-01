<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$title = Request::trim('title');
$url = Request::trim('url');
$version = Request::trim('version');

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