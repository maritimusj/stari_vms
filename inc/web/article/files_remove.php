<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
if ($id) {
    $archive = m('files')->findOne(We7::uniacid(['id' => $id]));
    if ($archive && $archive->destroy()) {
        Util::itoast('删除成功！', $this->createWebUrl('article', ['op' => 'files']), 'sucess');
    }
}

Util::itoast('删除失败！', $this->createWebUrl('article', ['op' => 'files']), 'error');