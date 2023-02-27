<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = request::int('id');
if ($id) {
    $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
    if ($msg) {
        $msg->destroy();
        Util::itoast('删除成功！', $this->createWebUrl('agent', ['op' => 'msg']), 'success');
    }
}

Util::itoast('删除失败！', $this->createWebUrl('agent', ['op' => 'msg']), 'error');