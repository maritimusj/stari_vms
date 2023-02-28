<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$id = Request::int('id');
$app = m('agent_app')->findOne(We7::uniacid(['id' => $id]));

if ($app && $app->destroy()) {
    Util::itoast('删除成功！', $this->createWebUrl('agent', ['op' => 'app']), 'success');
}

Util::itoast('删除失败！', $this->createWebUrl('agent', ['op' => 'app']), 'error');