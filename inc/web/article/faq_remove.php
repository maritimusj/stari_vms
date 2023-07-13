<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $faq = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'faq']));
    if (empty($faq)) {
        Response::itoast('找不到这条FAQ！', $this->createWebUrl('article', ['op' => 'faq']), 'error');
    }
    if ($faq->destroy()) {
        Response::itoast('删除成功！', $this->createWebUrl('article', ['op' => 'faq']), 'sucess');
    }
}

Response::itoast('删除失败！', $this->createWebUrl('article', ['op' => 'faq']), 'error');