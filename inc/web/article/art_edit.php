<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [
    'art_types' => [
        'article' => [],
        'faq' => [],
    ],
    'archive_types' => settings('doc.types'),
];

$id = Request::int('id');
if ($id) {
    $art = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'article']));
    if (empty($art)) {
        Response::itoast('找不到这篇文章！', $this->createWebUrl('article'), 'error');
    }
    $tpl_data['id'] = $id;
    $tpl_data['art'] = $art;
}

app()->showTemplate('web/doc/article_edit', $tpl_data);