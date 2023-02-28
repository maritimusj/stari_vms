<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\msgModelObj;

$id = Request::int('id');

$title = Request::trim('title');
$content = Request::str('content');

if ($id) {
    /** @var msgModelObj $msg */
    $msg = m('msg')->findOne(We7::uniacid(['id' => $id]));
    if ($msg) {
        if ($msg->getTitle() != $title) {
            $msg->setTitle($title);
        }

        if ($msg->getContent() != $content) {
            $msg->setContent($content);
        }
    }
}

if (empty($msg)) {
    $msg = m('msg')->create(We7::uniacid(['title' => $title, 'content' => $content]));
}

if ($msg && $msg->save()) {
    Util::itoast('保存成功！', $this->createWebUrl('agent', ['op' => 'msg']), 'success');
}

Util::itoast('保存失败！', $this->createWebUrl('agent', ['op' => 'msg']), 'error');