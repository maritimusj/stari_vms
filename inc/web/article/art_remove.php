<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $article = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'article']));
    if (empty($article)) {
        Response::toast('找不到这篇文章！', $this->createWebUrl('article'), 'error');
    }
    if ($article->destroy()) {
        Response::toast('删除成功！', $this->createWebUrl('article'), 'success');
    }
}

Response::toast('删除失败！', $this->createWebUrl('article'), 'success');