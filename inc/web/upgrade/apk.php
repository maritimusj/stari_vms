<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

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